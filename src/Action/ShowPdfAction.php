<?php

declare(strict_types=1);

namespace Lle\PdfGeneratorBundle\Action;


use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ShowPdfAction
{
    private $pdfGenerator;

    public function __construct(PdfGenerator $pdfGenerator)
    {
        $this->pdfGenerator = $pdfGenerator;
    }

    public function __invoke(Request $request): Response
    {
        $model = $this->pdfGenerator->getRepository()->find($request->get('id'));
        return $this->pdfGenerator->generateResponse($model->getCode(), [[]]);
    }

}