<?php

namespace Lle\PdfGeneratorBundle\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

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
        $model = $this->pdfGenerator->getRepository()->find($request->attributes->get('id'));

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
        $model = $this->pdfGenerator->getRepository()->find($request->attributes->get('id'));

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

        $model = $this->pdfGenerator->getRepository()->find($request->attributes->get('id'));

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

    #[Route('/balises', name: 'lle_pdf_generator_admin_balise')]
    public function balise(
        ParameterBagInterface $parameterBag,
    ): Response {
        $models = $parameterBag->get('lle.pdf.data_models');
        return $this->render('@LlePdfGenerator/balise/index.html.twig', [
            'models' => $models,
        ]);
    }

    #[Route('/balises/{module}', name: 'lle_pdf_generator_admin_model_balise')]
    public function getBalises(array $module): Response
    {
        $classes = [];

        $annotationReader = new AnnotationReader();
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();

        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metaDataEntity) {
            if (!$metaDataEntity->getReflectionClass()->isAbstract() && strstr($metaDataEntity->getName(), 'App')) {
                $fields = [];
                $prefix = $nameConverter->normalize($metaDataEntity->getReflectionClass()->getShortName());

                // Groupe PdfGenerator attribute
                foreach ($metaDataEntity->getReflectionClass()->getProperties() as $property) {
                    $annotationName = $annotationReader->getPropertyAnnotation(
                        $property,
                        'Symfony\Component\Serializer\Attribute\Groups'
                    );

                    /** @phpstan-ignore-next-line */
                    if ($annotationName && in_array($module, $annotationName->getGroups())) {
                        $fields[] = $prefix . '.' . $nameConverter->normalize($property->name);
                    }
                }

                // Groupe PdfGenerator getter
                foreach ($metaDataEntity->getReflectionClass()->getMethods() as $method) {
                    $annotationName = $annotationReader->getMethodAnnotation(
                        $method,
                        'Symfony\Component\Serializer\Attribute\Groups'
                    );

                    /** @phpstan-ignore-next-line */
                    if ($annotationName && in_array($module, $annotationName->getGroups())) {
                        $fields[] = $prefix . '.' . $nameConverter->normalize(str_replace('get', '', $method->name));
                    }
                }

                if ($fields) {
                    $classes[$metaDataEntity->getName()] = $fields;
                }
            }
        }

        $balises = [];
        foreach ($classes as $key => $values) {
            foreach ($values as $k => $v) {
                $caption = 'field.' . $v;

                $balises[] = [$v => $caption];
            }
        }

        return $this->render('@LlePdfGenerator/balise/balises.html.twig', [
            'balisesArray' => $balises,
            'module' => $module,
        ]);
    }
}
