<?php

namespace Lle\PdfGeneratorBundle\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class BaliseController extends AbstractController
{
    /**
     * @Route("/admin/balise", name="lle_pdf_generator_admin_balise")
     */
    public function __invoke()
    {
        $em = $this->getDoctrine()->getManager();
        $classes = [];
        $annotationReader = new AnnotationReader();
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();
        foreach ($em->getMetadataFactory()->getAllMetadata() as $metaDataEntity) {
            if (!$metaDataEntity->getReflectionClass()->isAbstract() && strstr($metaDataEntity->getName(), 'App')) {
                $fields = [];
                $prefix = $nameConverter->normalize($metaDataEntity->getReflectionClass()->getShortName());

                // attribut groupe pdfgenerator
                foreach ($metaDataEntity->getReflectionClass()->getProperties() as $property) {
                    $annotationName = $annotationReader->getPropertyAnnotation( $property, 'Symfony\Component\Serializer\Annotation\Groups');

                    if ($annotationName && in_array('pdfgenerator',$annotationName->getGroups())) {
                        $fields[] = $prefix.'.'.$nameConverter->normalize($property->name);
                    }
                }

                // getter groupe pdfgenerator
                foreach ($metaDataEntity->getReflectionClass()->getMethods() as $method) {
                    $annotationName = $annotationReader->getMethodAnnotation($method, 'Symfony\Component\Serializer\Annotation\Groups');
                    if ($annotationName && in_array('pdfgenerator',$annotationName->getGroups())) {
                        $fields[] =   $prefix.'.'.$nameConverter->normalize(str_replace('get','',$method->name));
                    }
                }

                if ($fields) $classes[$metaDataEntity->getName()] = $fields;

            }
        }

        $balises = [];
        foreach ($classes as $key=>$values) {
            foreach ($values as $k=>$v) {
                $caption = 'field.' . $v;
                $balises[] = [$v => $caption];
            }
        }

        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory);

        return $this->render('@LlePdfGenerator/balise/index.html.twig', [
            'balisesArray' => $balises
        ]);
    }
}
