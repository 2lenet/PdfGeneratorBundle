<?php

declare(strict_types=1);

namespace Lle\PdfGeneratorBundle\Action;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowRessourceAction
{
    public function __construct(private PdfGenerator $pdfGenerator)
    {
    }

    public function __invoke(Request $request): Response
    {
        $model = $this->pdfGenerator->getRepository()->find($request->get('id'));

        if ($model) {
            $response = new BinaryFileResponse($this->pdfGenerator->getPath() . $model->getPath());

            return $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $model->getPath());
        } else {
            throw new NotFoundHttpException();
        }
    }

}