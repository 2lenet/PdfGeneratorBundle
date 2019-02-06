<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Lle\PdfGeneratorBundle\Lib\Signature;
use setasign\Fpdi\TcpdfFpdi;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Lle\PdfGeneratorBundle\Lib\PdfMerger;

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

    private function generateByModel(PdfModel $model, iterable $parameters):PDFMerger{
        if(count($parameters) === 0){
            $parameters[] = [];
        }
        $generator = $this->generators[$model->getType() ?? $this->getDefaultgenerator()];
        $generator->setPdfPath($this->getPath());
        $pdf = new PDFMerger();
        $path = $generator->getRessource($model->getPath());

        foreach($parameters as $parameter){
            $tmpFile = tempnam(sys_get_temp_dir(), 'tmp').'.pdf';
            $generator->generate($path, [
                static::ITERABLE => [],
                static::VARS => $parameter
            ], $tmpFile);
            $pdf->addPDF($tmpFile, "all");
        }
        return $pdf;
    }

    public function generateByRessource(string $type, string $ressource, iterable $parameters = []):PDFMerger{
        $model = new PdfModel();
        $model->setType($type);
        $model->setPath($ressource);
        return $this->generateByModel($model, $parameters);
    }

    public function generate(string $code, iterable $parameters = []): PDFMerger
    {
        $model = $this->em->getRepository(PdfModel::class)->findOneBy(['code' => $code]);
        if ($model == null) {
            throw new \Exception("no model found");
        }
        return $this->generateByModel($model, $parameters);

    }

    public function signe(PdfMerger $pdfMerger, Signature $signature): TcpdfFpdi{
        return $signature->signe($pdfMerger);
    }

    public function signeTcpdfFpdi(TcpdfFpdi $pdf, Signature $signature): TcpdfFpdi{
        return $signature->signeTcpdfFpdi($pdf);
    }

    public function signes(PdfMerger $pdfMerger, array $signatures): TcpdfFpdi{
        $pdf = $pdfMerger->toTcpdfFpdi();
        foreach($signatures as $signature){
            $pdf = $this->signeTcpdfFpdi($pdf, $signature);
        }
        return $pdf;
    }

    public function generateByRessourceResponse(string $type, string $ressource, iterable $parameters = [], ?array $signatures = []): BinaryFileResponse{
        return $this->getPdfToResponse($this->generateByRessource($type, $ressource, $parameters), $signatures);
    }

    public function generateResponse(string $code, iterable $parameters = [], ?array $signatures = []): BinaryFileResponse{
        return $this->getPdfToResponse($this->generate($code, $parameters), $signatures);
    }

    private function getPdfToResponse(PDFMerger $pdf, ?array $signatures = []): BinaryFileResponse{
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp');
        if(count($signatures)){
            $pdf = $this->signes($pdf, $signatures);
            $pdf->Output($tmpFile,'F');
        }else{
            $pdf->merge('file', $tmpFile);
        }
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