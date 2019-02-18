<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfGenerator
{

    const ITERABLE = 'iterable';
    const VARS = 'vars';

    private $em;
    private $parameterBag;
    private $kerne;
    private $generators = [];

    public function __construct(EntityManagerInterface $em, KernelInterface $kernel, ParameterBagInterface $parameterBag, iterable $pdfGenerators)
    {
        $this->em = $em;
        $this->parameterBag = $parameterBag;
        $this->kernel = $kernel;
        foreach($pdfGenerators as $pdfGenerator){
            $this->generators[$pdfGenerator->getName()] = $pdfGenerator;
        }
    }

    private function generateByModel(PdfModel $model, iterable $parameters):\PDFMerger{
        if(count($parameters) === 0){
            $parameters[] = [];
        }
        $pdf = new \PDFMerger();
        foreach($parameters as $parameter){
            foreach(explode(',', $model->getPath()) as $k => $ressource){
                $generator = $this->generators[explode(',', $model->getType())[$k] ?? $this->getDefaultgenerator()];
                $tmpFile = tempnam(sys_get_temp_dir(), 'tmp').'.pdf';
                $generator->generate($generator->getRessource($this->getPath(),$ressource), [static ::ITERABLE => [],
                static ::VARS => $parameter], $tmpFile);
                $pdf->addPDF($tmpFile, "all");
            }
        }
        return $pdf;
    }

    public function generateByRessource(string $type, string $ressource, iterable $parameters = []):\PDFMerger{
        $model = new PdfModel();
        $model->setType($type);
        $model->setPath($ressource);
        return $this->generateByModel($model, $parameters);
    }

    public function generate(string $code, iterable $parameters = []): \PDFMerger
    {
        $model = $this->em->getRepository(PdfModel::class)->findOneBy(['code' => $code]);
        if ($model == null) {
            throw new \Exception("no model found");
        }
        return $this->generateByModel($model, $parameters);

    }

    public function generateByRessourceResponse(string $type, string $ressource, iterable $parameters = []): BinaryFileResponse{
        return $this->getPdfToResponse($this->generateByRessource($type, $ressource, $parameters));
    }

    public function generateResponse(string $code, iterable $parameters = []): BinaryFileResponse{
        return $this->getPdfToResponse($this->generate($code, $parameters));
    }

    private function getPdfToResponse(\PDFMerger $pdf): BinaryFileResponse{
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp');
        $pdf->merge('file', $tmpFile);
        return new BinaryFileResponse($tmpFile);
    }

    public function getPath(): string
    {
        return $this->kernel->getProjectDir().'/'.$this->parameterBag->get('lle.pdf.path').'/';
    }

    public function getDefaultGenerator():string{
        return $this->parameterBag->get('lle.pdf.default_generator');
    }

    public function getTypes(): array{
        return array_keys($this->generators);
    }
}