<?php

namespace Lle\PdfGeneratorBundle\Generator;



interface PdfGeneratorInterface{

    public static function getName():string;
    public function generate(string $source, iterable $params, string $savePath):void;
    public function getRessource(string $pdfPath, string $modelRessource): string;
}