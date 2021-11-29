<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Exception\ModelNotFoundException;
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

    const OPTION_EMPTY_NOTFOUND_VALUE = 'oenv';

    private $em;
    private $parameterBag;
    private $kerne;
    private $generators = [];
    private $criteria;
    private $options = [];

    public function __construct(EntityManagerInterface $em, KernelInterface $kernel, ParameterBagInterface $parameterBag, iterable $pdfGenerators)
    {
        $this->em = $em;
        $this->parameterBag = $parameterBag;
        $this->kernel = $kernel;
        $this->criteria = [];
        foreach($pdfGenerators as $pdfGenerator){
            $this->generators[$pdfGenerator->getName()] = $pdfGenerator;
        }
    }

    public function addOption($key, $val){
        $this->options[$key] = $val;
    }

    public function generateByModel(PdfModelInterface $model, iterable $parameters):PDFMerger{
        if(count($parameters) === 0){
            $parameters[] = [];
        }

        $pdf = new PdfMerger();
        foreach($parameters as $parameter) {
            foreach (explode(',', $model->getPath()) as $k => $ressource) {

                // instanciate the generator type from model type
                $types = explode(',', $model->getType());
                if(isset($this->generators[$types[$k] ?? $types[0]])){
                    $generator = $this->generators[$types[$k] ?? $types[0]];
                }else{
                    /** @var PdfGeneratorInterface $generator */
                    $generator = $this->generators[$this->getDefaultgenerator()];
                }

                $generator->setPdfPath($this->getPath());
                $tmpFile = tempnam(sys_get_temp_dir(), 'tmp') . '.pdf';
                $r = $generator->getRessource($ressource);
                $generator->generate($r , $parameter, $tmpFile, $this->options);
                $pdf->addPDF($tmpFile, "all");
            }
        }
        return $pdf;
    }

    public function generateByRessource($type, $ressource, iterable $parameters = []):PDFMerger{
        $model = $this->newInstance();
        $model->setType(is_array($type)? implode(',',$type):$type);
        $model->setPath(is_array($ressource)? implode(',',$ressource):$ressource);
        return $this->generateByModel($model, $parameters);
    }

    public function generate(string $code, iterable $datas = []): PDFMerger
    {
        $model = $this->getRepository()->findOneBy($this->getCriteria($code));
        if ($model == null) {
            throw new ModelNotFoundException("no model found (".$code.")");
        }
        return $this->generateByModel($model, $datas);

    }

    public function getCriteria($code){
        return array_merge(['code' => $code],$this->criteria);
    }

    public function setCriteria(array $criteria){
        $this->criteria = $criteria;
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

    public function generateByModelResponse(PdfModelInterface $model, iterable $parameters = [], ?array $signatures = []): BinaryFileResponse{
        return $this->getPdfToResponse($this->generateByModel($model, $parameters), $signatures);
    }

    public function generateResponse(string $code, iterable $parameters = [], ?array $signatures = []): BinaryFileResponse{
        return $this->getPdfToResponse($this->generate($code, $parameters), $signatures);
    }

    public function generatePath(string $code, iterable $parameters = [], ?array $signatures = []): string{
        return $this->getPdfToPath($this->generate($code, $parameters), $signatures);
    }

    private function getPdfToResponse(PDFMerger $pdf, ?array $signatures = []): BinaryFileResponse{
        return new BinaryFileResponse($this->getPdfToPath($pdf, $signatures));
    }

    private function getPdfToPath(PDFMerger $pdf, ?array $signatures = []): string{
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp').'.pdf';
        if(count($signatures)){
            $pdf = $this->signes($pdf, $signatures);
            $pdf->Output($tmpFile,'F');
        }else{
            $pdf->merge('file', $tmpFile);
        }
        return $tmpFile;
    }

    public function newInstance(): PdfModelInterface{
        return $this->em->getClassMetadata($this->parameterBag->get('lle.pdf.class'))->newInstance();
    }

    public function getRepository(){
        return $this->em->getRepository($this->parameterBag->get('lle.pdf.class'));
    }

    public function getPath(): string
    {
        return $this->kernel->getProjectDir().'/'.$this->parameterBag->get('lle.pdf.path').'/';
    }

    public function getDefaultGenerator():string
    {
        return $this->parameterBag->get('lle.pdf.default_generator');
    }

    public function getTypes(): array
    {
        return array_keys($this->generators);
    }

    public function getDataModels(): array
    {
        return $this->parameterBag->get('lle.pdf.data_models');
    }
}
