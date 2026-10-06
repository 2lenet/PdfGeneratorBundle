<?php

namespace Lle\PdfGeneratorBundle\Controller;

use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Lle\PdfGeneratorBundle\Security\PdfModelRoles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/** Download and preview of a PDF template (roles of the PDFMODEL screen). */
#[Route('/pdfmodel')]
class PdfGenController extends AbstractController
{
    public function __construct(
        protected PdfGenerator $pdfGenerator,
    ) {
    }

    #[Route('/download/{id}', name: 'lle_pdf_generator_download_model', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadModele(int $id): Response
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::SHOW);
        $model = $this->getModel($id);
        // the file must stay in the templates folder (path imported from another platform…)
        $root = realpath($this->pdfGenerator->getPath());
        $file = realpath($this->pdfGenerator->getPath() . $model->getPath());
        if (!$model->getPath() || !$root || !$file || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
            throw $this->createNotFoundException('File of the template ' . $model->getCode() . ' not found');
        }

        $response = $this->file($file);

        return $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, (string)$model->getPath());
    }

    /** PDF of the template rendered without data (test data for a crudit_report template). */
    #[Route('/pdf/{id}', name: 'lle_pdf_generator_show_model', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showModele(int $id): Response
    {
        $this->denyAccessUnlessGranted(PdfModelRoles::SHOW);

        return $this->pdfGenerator->generateResponse((string)$this->getModel($id)->getCode(), [[]]);
    }

    private function getModel(int $id): PdfModelInterface
    {
        $model = $this->pdfGenerator->getRepository()->find($id);

        if (!$model instanceof PdfModelInterface) {
            throw $this->createNotFoundException('Template ' . $id . ' not found');
        }

        return $model;
    }
}
