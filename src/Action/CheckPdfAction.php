<?php

declare(strict_types=1);

namespace Lle\PdfGeneratorBundle\Action;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CheckPdfAction
{
    public function __construct(
        private PdfGenerator $pdfGenerator,
        private EntityManagerInterface $em
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        /** @var Session $session */
        $session = $request->getSession();
        $flashBag = $session->getFlashBag();

        $model = $this->pdfGenerator->getRepository()->find($request->get('id'));

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
                $flashBag->add('error', 'Une erreur est survenue, il est impossible de générer un PDF avec les données actuel de ce modèle');
            }

            return new RedirectResponse($request->server->get('HTTP_REFERER'));
        } else {
            throw new NotFoundHttpException();
        }
    }
}