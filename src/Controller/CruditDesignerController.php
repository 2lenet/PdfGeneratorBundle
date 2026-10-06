<?php

namespace Lle\PdfGeneratorBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Exception\LibraryFileExistsException;
use Lle\PdfGeneratorBundle\Generator\CruditReportGenerator;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Lle\PdfGeneratorBundle\Security\PdfModelRoles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Crudit Report designer: opening of a crudit_report template, reading and saving of its JSON,
 * library of images and fonts (templates folder): reading and upload of files.
 * A template that has a data source (PdfModel::datasource) receives on reading the current parameters of the
 * source and its sample as test data; on saving, its provided parameters are reset from the source (the designer
 * cannot change them).
 */
#[Route('/pdfmodel')]
class CruditDesignerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfGenerator $pdfGenerator,
        private CruditReportGenerator $cruditReportGenerator,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    /** Opens the designer on the template; ?back=: return address (default: previous page). */
    #[Route('/designer/{id}', name: 'lle_pdf_generator_crudit_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(Request $request, int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::DESIGNER);
        $model = $this->getModel($id);

        // the library is a base URL: route generated with a dummy path, removed afterwards
        $library = $this->generateUrl('lle_pdf_generator_crudit_library', ['path' => '_']);
        $params = array_filter([
            'template' => $this->generateUrl('lle_pdf_generator_crudit_template', ['id' => $id]),
            'library' => substr($library, 0, -1),
            'files' => $this->generateUrl('lle_pdf_generator_crudit_library_list'),
            'back' => $request->query->get('back') ?? $request->headers->get('referer'),
            'title' => $model->getLibelle(),
        ]);

        $designer = $this->parameterBag->get('lle.pdf.crudit.designer_url');

        // designer served by the project (public/): its deployment date in the URL, so that the browser does not
        // take an old index.html from its cache (which would load the old engine)
        if (!preg_match('#^([a-z]+:)?//#i', $designer)) {
            $index = $this->parameterBag->get('kernel.project_dir') . '/public' . parse_url($designer, PHP_URL_PATH);
            if (!is_file($index)) {
                throw new \RuntimeException(
                    'Crudit Report designer not found: public' . parse_url($designer, PHP_URL_PATH) . ' (lle_pdf_generator.crudit.designer_url)'
                );
            }
            $params['v'] = (string)filemtime($index);
        }

        return $this->redirect($designer . (str_contains($designer, '?') ? '&' : '?') . http_build_query($params));
    }

    #[Route('/template/{id}', name: 'lle_pdf_generator_crudit_template', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function read(int $id): Response
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::DESIGNER);
        $model = $this->getModel($id);
        $file = $this->getTemplateFile($model);
        $headers = ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'];

        $template = json_decode((string)file_get_contents($file), false);
        if (!$template instanceof \stdClass) {
            return new BinaryFileResponse($file, 200, $headers);
        }

        if ($model->getDatasource()) {
            $template = $this->cruditReportGenerator->applyDataSource($template, $model->getDatasource());
            // a sample that cannot be produced (empty database…) does not prevent opening the template: testData is kept
            try {
                $sample = $this->cruditReportGenerator->getDataSourceSample($model->getDatasource());
            } catch (\Throwable) {
                $sample = null;
            }
            if ($sample !== null) {
                $template->testData = $sample;
            }
        } else {
            $template = $this->cruditReportGenerator->removeDataSource($template);
        }

        return new Response($this->cruditReportGenerator->encode($template), 200, $headers);
    }

    /** Saves the template sent by the designer, if valid (otherwise 422 with the diagnostics). */
    #[Route('/template/{id}', name: 'lle_pdf_generator_crudit_template_save', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function save(Request $request, int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::DESIGNER);
        $model = $this->getModel($id);
        $file = $this->getTemplateFile($model);

        $json = $request->getContent();
        $template = json_decode($json, false);
        if (!$template instanceof \stdClass) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }
        // parameters provided by the source: always those of the PHP, whatever the designer sends
        if ($model->getDatasource()) {
            $json = $this->cruditReportGenerator->encode(
                $this->cruditReportGenerator->applyDataSource($template, $model->getDatasource())
            );
        } elseif (isset($template->designer->source)) {
            $json = $this->cruditReportGenerator->encode($this->cruditReportGenerator->removeDataSource($template));
        }

        $result = $this->cruditReportGenerator->validate($json);
        if (!$result['ok']) {
            return new JsonResponse($result, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // atomic write: rendering never reads a half-written file
        $tmp = $file . '.' . uniqid() . '.tmp';
        file_put_contents($tmp, $json);
        chmod($tmp, fileperms($file) & 0o777);
        rename($tmp, $file);

        $model->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return new JsonResponse($result);
    }

    /** Images and fonts of the templates folder (assets ref of the template). */
    #[Route('/library/{path}', name: 'lle_pdf_generator_crudit_library', requirements: ['path' => '.+'], methods: ['GET'])]
    public function library(string $path): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::DESIGNER);

        $root = realpath($this->pdfGenerator->getPath());
        $file = realpath($this->pdfGenerator->getPath() . $path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (
            !$root || !$file || !is_file($file)
            || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)
            || !isset(CruditReportGenerator::LIBRARY_EXTENSIONS[$extension])
        ) {
            throw new NotFoundHttpException();
        }

        // an SVG opened directly in the browser must not run scripts on the application domain
        return new BinaryFileResponse($file, 200, [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            // a file can be overwritten: the browser revalidates instead of keeping the old one
            'Cache-Control' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Images and fonts of the library, shared by all the templates: { files: [{ uri, kind, … }] }. */
    #[Route('/library', name: 'lle_pdf_generator_crudit_library_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::DESIGNER);

        return new JsonResponse(
            ['files' => $this->cruditReportGenerator->listLibraryFiles($this->pdfGenerator->getPath())],
            Response::HTTP_OK,
            ['Cache-Control' => 'no-store'],
        );
    }

    /**
     * Upload of an image or a font to the library (file field, multipart): returns { uri }, to reference in the
     * template. Another file with the same name gives a 409 { error, uri }, unless the overwrite=1 field is set.
     * Restricted to the designer (X-Requested-With header), which rules out a form from another site.
     */
    #[Route('/library', name: 'lle_pdf_generator_crudit_library_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::DESIGNER);

        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => 'Request refused'], Response::HTTP_BAD_REQUEST);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $error = $file instanceof UploadedFile ? $file->getErrorMessage() : 'No file uploaded';

            return new JsonResponse(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        try {
            $uri = $this->cruditReportGenerator->storeLibraryFile(
                $this->pdfGenerator->getPath(),
                $file->getPathname(),
                $file->getClientOriginalName(),
                $request->request->getBoolean('overwrite'),
            );
        } catch (LibraryFileExistsException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'uri' => $e->uri], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['uri' => $uri], Response::HTTP_CREATED);
    }

    private function getModel(int $id): PdfModelInterface
    {
        $model = $this->pdfGenerator->getRepository()->find($id);

        if (!$model instanceof PdfModelInterface || $model->getType() !== CruditReportGenerator::getName()) {
            throw new NotFoundHttpException('Template ' . $id . ' not found or not of type ' . CruditReportGenerator::getName());
        }

        return $model;
    }

    private function getTemplateFile(PdfModelInterface $model): string
    {
        $file = $this->pdfGenerator->getPath() . $model->getPath();

        if (!$model->getPath() || !is_file($file)) {
            throw new NotFoundHttpException('File of the template ' . $model->getCode() . ' not found');
        }

        return $file;
    }
}
