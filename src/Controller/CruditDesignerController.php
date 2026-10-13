<?php

namespace Lle\PdfGeneratorBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Exception\LibraryFileExistsException;
use Lle\PdfGeneratorBundle\Generator\CruditReportGenerator;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Lle\PdfGeneratorBundle\Library\TemplateLibrary;
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
 * Concurrent saves: the template is read with an ETag (hash of its file) and saved with If-Match; a template saved
 * elsewhere in the meantime gives a 412, so that a save never overwrites a newer version without warning.
 */
#[Route('/pdfmodel')]
class CruditDesignerController extends AbstractController
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected PdfGenerator $pdfGenerator,
        protected CruditReportGenerator $cruditReportGenerator,
        protected TemplateLibrary $library,
        protected ParameterBagInterface $parameterBag,
    ) {
    }

    /** Opens the designer on the template; ?back=: return address of the same site (default: previous page). */
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
            'back' => $this->sameSite($request, $request->query->get('back') ?? $request->headers->get('referer')),
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
        $content = (string)file_get_contents($file);
        // version of the saved file, not of the response (completed with the data source)
        $headers = ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store', 'ETag' => self::etag($content)];

        $template = json_decode($content, false);
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

    /**
     * Saves the template sent by the designer, if valid (otherwise 422 with the diagnostics). With If-Match, a template
     * saved elsewhere since it was read gives a 412. The response carries the ETag of the saved template.
     * Restricted to the designer (X-Requested-With header), like the upload.
     */
    #[Route('/template/{id}', name: 'lle_pdf_generator_crudit_template_save', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function save(Request $request, int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::DESIGNER);
        $model = $this->getModel($id);
        $file = $this->getTemplateFile($model);

        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => 'Request refused'], Response::HTTP_BAD_REQUEST);
        }

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

        // the version check and the write are done under a lock: two saves of the same version cannot both pass
        $lock = fopen($file, 'r') ?: throw new \RuntimeException('Cannot read the template ' . $model->getCode());
        try {
            flock($lock, LOCK_EX);
            if (!self::matches($request->headers->get('If-Match'), (string)file_get_contents($file))) {
                return new JsonResponse(
                    ['error' => 'The template was modified in the application since it was opened'],
                    Response::HTTP_PRECONDITION_FAILED,
                );
            }

            // atomic write: rendering never reads a half-written file
            $tmp = $file . '.' . uniqid() . '.tmp';
            if (file_put_contents($tmp, $json) !== strlen($json)) {
                @unlink($tmp);

                throw new \RuntimeException('Cannot write the template ' . $model->getCode());
            }
            chmod($tmp, fileperms($file) & 0o777);
            rename($tmp, $file) ?: throw new \RuntimeException('Cannot write the template ' . $model->getCode());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $model->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return new JsonResponse($result, Response::HTTP_OK, ['ETag' => self::etag($json)]);
    }

    /** Version of a saved template (ETag): hash of its file. */
    public static function etag(string $content): string
    {
        return '"' . substr(hash('sha256', $content), 0, 32) . '"';
    }

    /**
     * Whether the If-Match header accepts the saved template: no header (no check), *, or one of its ETags.
     * A weak prefix (W/) and the suffix added by a compressing proxy (Apache mod_deflate: "…-gzip") are ignored.
     */
    public static function matches(?string $ifMatch, string $content): bool
    {
        if ($ifMatch === null || trim($ifMatch) === '*') {
            return true;
        }
        $current = self::etag($content);
        foreach (explode(',', $ifMatch) as $tag) {
            if (preg_replace('#^W/|-(gzip|br|deflate|zstd)(?="$)#', '', trim($tag)) === $current) {
                return true;
            }
        }

        return false;
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
            || !isset(TemplateLibrary::EXTENSIONS[$extension])
            // hidden elements (.import-…, .backup-… of the templates import) are not part of the library
            || preg_match('#(^|/)\.#', str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($root) + 1)))
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
            ['files' => $this->library->list($this->pdfGenerator->getPath())],
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
            $uri = $this->library->store(
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

    /**
     * $url if it is a path or an http(s) URL of the application host, otherwise null: the designer shows it as a link,
     * which must not lead to another site nor run a javascript: URL.
     */
    private function sameSite(Request $request, ?string $url): ?string
    {
        if ($url === null || $url === '' || preg_match('/[\x00-\x20\\\\]/', $url)) {
            return null;
        }
        if (str_starts_with($url, '/')) {
            return str_starts_with($url, '//') ? null : $url;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && strtolower($parts['host'] ?? '') === strtolower($request->getHost())
            ? $url : null;
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

        if (!$model->getPath() || !PdfGenerator::isRelativePath($model->getPath()) || !is_file($file)) {
            throw new NotFoundHttpException('File of the template ' . $model->getCode() . ' not found');
        }

        return $file;
    }
}
