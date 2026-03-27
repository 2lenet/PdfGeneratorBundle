<?php

namespace Lle\PdfGeneratorBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pdfgen')]
class PdfGenController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdfGenerator $pdfGenerator,
    ) {
    }

    #[Route('/downloadModele', name: 'lle_pdf_generator_download_model')]
    public function downloadModele(Request $request): Response
    {
        $model = $this->pdfGenerator->getRepository()->find($request->query->get('id'));

        if ($model) {
            $response = new BinaryFileResponse($this->pdfGenerator->getPath() . $model->getPath());

            return $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $model->getPath());
        } else {
            throw new NotFoundHttpException();
        }
    }

    #[Route('/showModele', name: 'lle_pdf_generator_show_model')]
    public function showModele(Request $request): Response
    {
        $model = $this->pdfGenerator->getRepository()->find($request->query->get('id'));

        if ($model) {
            return $this->pdfGenerator->generateResponse($model->getCode(), [[]]);
        } else {
            throw new NotFoundHttpException();
        }
    }

    #[Route('/checkModele', name: 'lle_pdf_generator_check_model')]
    public function checkModele(Request $request): RedirectResponse
    {
        /** @var Session $session */
        $session = $request->getSession();
        $flashBag = $session->getFlashBag();

        $model = $this->pdfGenerator->getRepository()->find($request->query->get('id'));

        if ($model) {
            $model->setCheckFile(true);

            try {
                $this->pdfGenerator->generateResponse($model->getCode(), [[]]);
            } catch (\Exception $e) {
                $model->setCheckFile(false);
            }

            $this->em->persist($model);
            $this->em->flush();

            if ($model->getCheckFile()) {
                $flashBag->add('success', 'Fichier valider');
            } else {
                $flashBag->add(
                    'error',
                    'Une erreur est survenue, il est impossible de générer un PDF avec les données actuel de ce modèle'
                );
            }

            return new RedirectResponse($request->server->get('HTTP_REFERER'));
        } else {
            throw new NotFoundHttpException();
        }
    }
}
