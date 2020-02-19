<?php

namespace Lle\PdfGeneratorBundle\Generator;

use PhpOffice\PhpWord\TemplateProcessor;

abstract class AbstractPdfGenerator implements PdfGeneratorInterface {

    protected $pdfPath;

    public static function getName(): string{
        return static::class;
    }

    public function setPdfPath(string $pdfPath): void{
        $this->pdfPath = $pdfPath;
    }
    
    public function getRessource(string $modelRessource): string{
        return $this->pdfPath.$modelRessource;
    }

    public function getVariables(string $source):array
    {
        return [];
    }

}
