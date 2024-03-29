<?php

namespace Lle\PdfGeneratorBundle\Generator;

abstract class AbstractPdfGenerator implements PdfGeneratorInterface
{
    protected string $pdfPath;

    public static function getName(): string
    {
        return static::class;
    }

    public function setPdfPath(string $pdfPath): void
    {
        $this->pdfPath = $pdfPath;
    }

    public function getRessource(string $modelRessource): string
    {
        return $this->pdfPath . $modelRessource;
    }

    public function getVariables(string $source): array
    {
        return [];
    }
}
