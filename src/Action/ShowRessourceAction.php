<?php

declare(strict_types=1);

namespace Lle\PdfGeneratorBundle\Action;


use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ShowRessourceAction
{
    private $pdfGenerator;
    private $em;

    public function __construct(EntityManagerInterface $em, PdfGenerator $pdfGenerator)
    {
        $this->pdfGenerator = $pdfGenerator;
        $this->em = $em;
    }

    public function __invoke(Request $request): Response
    {
        $model = $this->em->getRepository(PdfModel::class)->find($request->get('id'));
        return new BinaryFileResponse($this->pdfGenerator->getPath().$model->getPath());
    }

}