<?php

namespace Lle\PdfGeneratorBundle\Generator;

interface PdfGeneratorInterface
{
    public static function getName(): string;

    public function generate(string $source, iterable $params, string $savePath, array $options = []): void;

    public function getRessource(string $modelRessource): string;

    public function setPdfPath(string $pdfPath): void;

    public function getVariables(string $source): array;
}