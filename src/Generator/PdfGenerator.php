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

    public function generateByModel(PdfModel $model, iterable $parameters):PDFMerger{
        if(count($parameters) === 0){
            $parameters[] = [];
        }

        $pdf = new PdfMerger();
        foreach($parameters as $parameter) {
            foreach (explode(',', $model->getPath()) as $k => $ressource) {
                $types = explode(',', $model->getType());
                $generator = $this->generators[$types[$k] ?? $types[0] ?? $this->getDefaultgenerator()];
                $generator->setPdfPath($this->getPath());
                $tmpFile = tempnam(sys_get_temp_dir(), 'tmp') . '.pdf';
                $r = $generator->getRessource($ressource);
                $generator->generate($r , $parameter, $tmpFile);
                $pdf->addPDF($tmpFile, "all");
            }
        }
        return $pdf;
    }

    public function generateByRessource($type, $ressource, iterable $parameters = []):PDFMerger{
        $model = new PdfModel();
        $model->setType(is_array($type)? implode(',',$type):$type);
        $model->setPath(is_array($ressource)? implode(',',$ressource):$ressource);
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

    public function generateByRessourceResponse($type, $ressource, iterable $parameters = [], ?array $signatures = []): BinaryFileResponse{
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
