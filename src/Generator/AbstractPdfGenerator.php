<?php

namespace Lle\PdfGeneratorBundle\Generator;

abstract class AbstractPdfGenerator implements PdfGeneratorInterface {

    public static function getName(): string{
        return static::class;
    }


    public function getRessource(string $pdfPath, string $modelRessource): string{
        return $pdfPath.$modelRessource;
    }
}