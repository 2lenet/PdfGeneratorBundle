<?php

namespace Lle\PdfGeneratorBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelVersion;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Lle\PdfGeneratorBundle\Repository\PdfModelVersionRepository;
use Lle\PdfGeneratorBundle\Security\PdfModelRoles;
use Lle\PdfGeneratorBundle\Version\VersionRestorer;
use Lle\PdfGeneratorBundle\Version\VersionStorage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/** History of the files of a template: list (tab of the show page), download and restoration of a version. */
#[Route('/pdfmodel/version')]
class PdfModelVersionController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'pdfmodel_version_restore';

    public function __construct(
        protected EntityManagerInterface $em,
        protected PdfGenerator $pdfGenerator,
        protected VersionStorage $storage,
        protected VersionRestorer $restorer,
        protected ?TranslatorInterface $translator = null,
        protected ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Versions of a template, the last one first. Not a route: rendered in the show page of a template by a Crudit
     * ControllerConfig (PdfModelVersionTab), which gives the ResourceView of the page, or with a template.
     */
    public function history(object $resource): Response
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::SHOW);
        $model = method_exists($resource, 'getResource') ? $resource->getResource() : $resource;
        if (!$model instanceof PdfModelInterface) {
            throw new \InvalidArgumentException('History of the files: a template (PdfModelInterface) is expected');
        }

        return $this->render('@LlePdfGenerator/version/history.html.twig', [
            'model' => $model,
            'versions' => $model->getId() !== null ? $this->repository()->findByModel($model->getId()) : [],
            'restoreRole' => PdfModelRoles::RESTORE,
            'csrfTokenId' => self::CSRF_TOKEN_ID,
        ]);
    }

    #[Route('/{id}', name: 'lle_pdf_generator_version_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(int $id): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::SHOW);
        $version = $this->getVersion($id);
        $file = $this->storage->path((string)$version->getStoredName());
        if (!is_file($file)) {
            throw $this->createNotFoundException('File of the version ' . $id . ' not found');
        }

        return $this->file($file, (string)$version->getOriginalName(), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    /** Puts the version back as the current file of its template (CSRF token _token), then goes back with a message. */
    #[Route('/{id}/restore', name: 'lle_pdf_generator_version_restore', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function restore(Request $request, int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::RESTORE);
        $version = $this->getVersion($id);
        $model = $this->pdfGenerator->getRepository()->find($version->getModelId());
        if (!$model instanceof PdfModelInterface) {
            throw $this->createNotFoundException('Template ' . $version->getModelId() . ' not found');
        }

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string)$request->request->get('_token'))) {
            $this->addFlash('danger', $this->trans('flash.pdfmodel_version_csrf'));
        } else {
            try {
                $this->restorer->restore($model, $version);
                $this->addFlash('success', $this->trans('flash.pdfmodel_version_restored', ['%number%' => (int)$version->getNumber()]));
            } catch (\RuntimeException $e) {
                $this->logger?->error('PDF template version restoration failed: ' . $e->getMessage(), ['exception' => $e]);
                $this->addFlash('danger', $this->trans('flash.pdfmodel_version_failed'));
            }
        }

        return $this->redirect($this->back($request));
    }

    private function getVersion(int $id): PdfModelVersion
    {
        return $this->repository()->find($id) ?? throw $this->createNotFoundException('Version ' . $id . ' not found');
    }

    private function repository(): PdfModelVersionRepository
    {
        /** @var PdfModelVersionRepository $repository */
        $repository = $this->em->getRepository(PdfModelVersion::class);

        return $repository;
    }

    /** Previous page, if it is on this site. */
    private function back(Request $request): string
    {
        $referer = (string)$request->headers->get('referer');

        return parse_url($referer, PHP_URL_HOST) === $request->getHost() ? $referer : '/';
    }

    /** @param array<string, string|int> $parameters */
    private function trans(string $key, array $parameters = []): string
    {
        return $this->translator?->trans($key, $parameters, 'PdfGeneratorBundle') ?? strtr($key, $parameters);
    }
}
