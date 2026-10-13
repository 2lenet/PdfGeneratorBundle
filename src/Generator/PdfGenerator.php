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
        $options = $this->options;
        if ($model->getCode()) {
            $options[CruditReportGenerator::OPTION_MODEL] = $model->getCode();
        }
        if ($model->getDatasource()) {
            $options[CruditReportGenerator::OPTION_DATASOURCE] = $model->getDatasource();
        }

        $ressources = self::paths((string)$model->getPath());
        foreach ($ressources as $ressource) {
            if ($ressource === '') {
                throw new \RuntimeException('PDF GENERATOR ERROR: ' . $model->getCode() . ': no template file');
            }
            if (!self::isRelativePath($ressource)) {
                throw new \RuntimeException('PDF GENERATOR ERROR: ' . $model->getCode() . ': path "' . $ressource . '" outside the templates folder');
            }
        }

        foreach ($parameters as $parameter) {
            foreach ($ressources as $k => $ressource) {
                // Instanciate the generator type from model type
                $types = explode(',', $model->getType());

                if (isset($this->generators[$types[$k] ?? $types[0]])) {
                    $generator = $this->generators[$types[$k] ?? $types[0]];
                } elseif (($types[$k] ?? $types[0]) === CruditReportGenerator::getName()) {
                    // not the default generator: it would fail on a .template.json with an unrelated error
                    throw new \RuntimeException(
                        'PDF GENERATOR ERROR: ' . $model->getCode() . ' is a crudit_report template, which is disabled (lle_pdf_generator.crudit.enabled)'
                    );
                } else {
                    /** @var PdfGeneratorInterface $generator */
                    $generator = $this->generators[$this->getDefaultgenerator()];
                }

                $generator->setPdfPath($this->getPath());
                $tmpFile = tempnam(sys_get_temp_dir(), 'tmp') . '.pdf';
                $r = $generator->getRessource($ressource);
                $generator->generate($r, $parameter, $tmpFile, $options);

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

    /**
     * Files of a template, separated by commas ("a.docx, b.docx").
     *
     * @return list<string>
     */
    public static function paths(string $path): array
    {
        return array_map('trim', explode(',', $path));
    }

    /**
     * A resource of a template (PdfModel::path) stays in the templates folder: relative, without "..". Also true for
     * a tcpdf class name.
     */
    public static function isRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || preg_match('#^([/\\\\]|[a-z]:)#i', $path)) {
            return false;
        }

        return !in_array('..', preg_split('#[/\\\\]#', $path) ?: [], true);
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
        return (new BinaryFileResponse($this->getPdfToPath($pdf, $signatures)))->deleteFileAfterSend();
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
