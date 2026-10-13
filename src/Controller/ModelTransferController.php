<?php

namespace Lle\PdfGeneratorBundle\Controller;

use Lle\PdfGeneratorBundle\Exception\ImportBackupException;
use Lle\PdfGeneratorBundle\Security\PdfModelRoles;
use Lle\PdfGeneratorBundle\Transfer\ModelTransfer;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Export and import of all the PDF templates (table and templates folder) in a zip archive, to copy them from one
 * platform to another. Restricted to the roles PdfModelRoles::EXPORT and PdfModelRoles::IMPORT.
 */
#[Route('/pdfmodel')]
class ModelTransferController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'lle_pdf_generator_models_import';

    public function __construct(
        protected ModelTransfer $transfer,
        protected ?TranslatorInterface $translator = null,
        protected ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/archive', name: 'lle_pdf_generator_models_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::EXPORT);
        if (!ModelTransfer::isAvailable()) {
            $this->addFlash('danger', $this->trans('flash.pdfmodel_zip_missing'));

            return $this->redirect($this->back($request));
        }

        $response = new BinaryFileResponse($this->transfer->export(), 200, ['Content-Type' => 'application/zip']);
        $response->deleteFileAfterSend();

        return $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'pdf-templates-' . preg_replace('/[^a-z0-9.-]+/i', '-', $request->getHost()) . '-' . date('Ymd-His') . '.zip'
        );
    }

    /**
     * Imports an archive from export() (archive field, CSRF token _token): replaces all the templates and their files.
     * Then goes back to the previous page (of the same site), with a flash message.
     */
    #[Route('/archive', name: 'lle_pdf_generator_models_import', methods: ['POST'])]
    public function import(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::IMPORT);
        $back = $this->back($request);

        $file = $request->files->get('archive');
        if (!ModelTransfer::isAvailable()) {
            $this->addFlash('danger', $this->trans('flash.pdfmodel_zip_missing'));
        } elseif (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string)$request->request->get('_token'))) {
            $this->addFlash('danger', $this->trans('flash.pdfmodel_import_csrf'));
        } elseif (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('danger', $this->trans('flash.pdfmodel_import_error', [
                '%error%' => $file instanceof UploadedFile ? $file->getErrorMessage() : $this->trans('flash.pdfmodel_import_no_file'),
            ]));
        } else {
            try {
                $result = $this->transfer->import($file->getPathname());
                $message = $this->trans('flash.pdfmodel_import_done', [
                    '%models%' => $result['models'],
                    '%files%' => $result['files'],
                ]);
                if ($result['ignored']) {
                    $message .= ' ' . $this->trans('flash.pdfmodel_import_ignored', ['%columns%' => implode(', ', $result['ignored'])]);
                }
                $this->addFlash('success', $message);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $this->trans('flash.pdfmodel_import_error', ['%error%' => $e->getMessage()]));
            } catch (\Throwable $e) {
                // database or files: nothing changed, unless old files are kept in a backup folder to copy back by hand;
                // the detail (SQL query…) is only logged
                $this->logger?->error('PDF templates import failed: ' . $e->getMessage(), ['exception' => $e]);
                $this->addFlash('danger', $e instanceof ImportBackupException
                    ? $this->trans('flash.pdfmodel_import_backup', ['%files%' => implode(', ', $e->lost), '%backup%' => $e->backup])
                    : $this->trans('flash.pdfmodel_import_failed'));
            }
        }

        return $this->redirect($back);
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
