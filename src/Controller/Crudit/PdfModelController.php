<?php

namespace Lle\PdfGeneratorBundle\Controller\Crudit;

use Lle\CruditBundle\Controller\AbstractCrudController;
use Lle\CruditBundle\Controller\TraitCrudController;
use Lle\PdfGeneratorBundle\Crudit\Config\PdfModelCrudConfig;
use Symfony\Component\Routing\Attribute\Route;

/** Admin screen of the PDF templates (routes lle_pdfgenerator_crudit_pdfmodel_*). */
#[Route('/pdfmodel')]
class PdfModelController extends AbstractCrudController
{
    use TraitCrudController;

    public function __construct(PdfModelCrudConfig $config)
    {
        $this->config = $config;
    }
}
