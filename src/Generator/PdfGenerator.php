<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Exception\ModelNotFoundException;
use Lle\PdfGeneratorBundle\Lib\PdfMerger;
use Lle\PdfGeneratorBundle\Lib\Signature;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class PdfGenerator
{
    public const OPTION_EMPTY_NOTFOUND_VALUE = 'oenv';

    private array $generators = [];

    private array $criteria;

    private array $options = [];

    public function __construct(
        private EntityManagerInterface $em,
        private KernelInterface $kernel,
        private ParameterBagInterface $parameterBag,
        iterable $pdfGenerators,
    ) {
        $this->criteria = [];
        foreach ($pdfGenerators as $pdfGenerator) {
            $this->generators[$pdfGenerator->getName()] = $pdfGenerator;
        }
    }

    public function addOption(string $key, mixed $val): void
    {
        $this->options[$key] = $val;
    }

    public function generateByModel(PdfModelInterface $model, iterable $parameters): PDFMerger
    {
        if (count($parameters) === 0) {
            $parameters[] = [];
        }

        $pdf = new PdfMerger();

        foreach ($parameters as $parameter) {
            foreach (explode(',', $model->getPath()) as $k => $ressource) {
                // Instanciate the generator type from model type
                $types = explode(',', $model->getType());

                if (isset($this->generators[$types[$k] ?? $types[0]])) {
                    $generator = $this->generators[$types[$k] ?? $types[0]];
                } else {
                    /** @var PdfGeneratorInterface $generator */
                    $generator = $this->generators[$this->getDefaultgenerator()];
                }

                $generator->setPdfPath($this->getPath());
                $tmpFile = tempnam(sys_get_temp_dir(), 'tmp') . '.pdf';
                $r = $generator->getRessource($ressource);
                $generator->generate($r, $parameter, $tmpFile, $this->options);

                $pdf->addPDF($tmpFile, "all");
            }
        }

        return $pdf;
    }

    public function generateByRessource(mixed $type, mixed $ressource, iterable $parameters = []): PDFMerger
    {
        $model = $this->newInstance();
        $model->setType(is_array($type) ? implode(',', $type) : $type);
        $model->setPath(is_array($ressource) ? implode(',', $ressource) : $ressource);

        return $this->generateByModel($model, $parameters);
    }

    public function generate(string $code, iterable $datas = []): PDFMerger
    {
        $model = $this->getRepository()->findOneBy($this->getCriteria($code));

        if (!$model) {
            throw new ModelNotFoundException("no model found (" . $code . ")");
        }

        return $this->generateByModel($model, $datas);
    }

    public function getCriteria(string $code): array
    {
        return array_merge(['code' => $code], $this->criteria);
    }

    public function setCriteria(array $criteria): self
    {
        $this->criteria = $criteria;

        return $this;
    }

    public function signe(PdfMerger $pdfMerger, Signature $signature): Fpdi
    {
        return $signature->signe($pdfMerger);
    }

    public function signeTcpdfFpdi(Fpdi $pdf, Signature $signature): Fpdi
    {
        return $signature->signeTcpdfFpdi($pdf);
    }

    public function signes(PdfMerger $pdfMerger, array $signatures): Fpdi
    {
        $pdf = $pdfMerger->toTcpdfFpdi();

        foreach ($signatures as $signature) {
            $pdf = $this->signeTcpdfFpdi($pdf, $signature);
        }

        return $pdf;
    }

    public function generateByRessourceResponse(
        mixed $type,
        mixed $ressource,
        iterable $parameters = [],
        ?array $signatures = [],
    ): BinaryFileResponse {
        return $this->getPdfToResponse($this->generateByRessource($type, $ressource, $parameters), $signatures);
    }

    public function generateByModelResponse(
        PdfModelInterface $model,
        iterable $parameters = [],
        ?array $signatures = [],
    ): BinaryFileResponse {
        return $this->getPdfToResponse($this->generateByModel($model, $parameters), $signatures);
    }

    public function generateResponse(
        string $code,
        iterable $parameters = [],
        ?array $signatures = [],
    ): BinaryFileResponse {
        return $this->getPdfToResponse($this->generate($code, $parameters), $signatures);
    }

    public function generatePath(string $code, iterable $parameters = [], ?array $signatures = []): string
    {
        return $this->getPdfToPath($this->generate($code, $parameters), $signatures);
    }

    private function getPdfToResponse(PDFMerger $pdf, ?array $signatures = []): BinaryFileResponse
    {
        return new BinaryFileResponse($this->getPdfToPath($pdf, $signatures));
    }

    private function getPdfToPath(PDFMerger $pdf, ?array $signatures = []): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp') . '.pdf';

        if (count($signatures)) {
            $pdf = $this->signes($pdf, $signatures);
            $pdf->Output($tmpFile, 'F');
        } else {
            $pdf->merge('file', $tmpFile);
        }

        return $tmpFile;
    }

    public function newInstance(): PdfModelInterface
    {
        /** @var class-string $class */
        $class = $this->parameterBag->get('lle.pdf.class');

        return $this->em->getClassMetadata($class)->newInstance();
    }

    public function getRepository(): ObjectRepository
    {
        /** @var class-string $pdfClass */
        $pdfClass = $this->parameterBag->get('lle.pdf.class');

        return $this->em->getRepository($pdfClass);
    }

    public function getPath(): string
    {
        return $this->kernel->getProjectDir() . '/' . $this->parameterBag->get('lle.pdf.path') . '/';
    }

    public function getDefaultGenerator(): string
    {
        return $this->parameterBag->get('lle.pdf.default_generator');
    }

    public function getTypes(): array
    {
        return array_keys($this->generators);
    }
}
