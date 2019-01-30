<?php

namespace Lle\PdfGeneratorBundle\Generator;

abstract class AbstractPdfGenerator implements PdfGeneratorInterface {

    public function getName(): string{
        return static::class;
    }


    public function getModelPath(string $pdfPath, string $modelPath): string{
        return $pdfPath.$modelPath;
    }
}