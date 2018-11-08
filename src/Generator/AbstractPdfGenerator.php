<?php

namespace Lle\PdfGeneratorBundle\Generator;

abstract class AbstractPdfGenerator implements PdfGeneratorInterface {

    public function getName(): string{
        return static::class;
    }
}