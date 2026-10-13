<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Lle\PdfGeneratorBundle\Lib\Pdf;

class TcpdfGenerator extends AbstractPdfGenerator
{
    public function generate(string $source, iterable $params, string $savePath, array $options = []): void
    {
        // checked before instantiating: the path of a template must not create any class of the application
        if (!is_subclass_of($source, Pdf::class)) {
            throw new \Exception('PDF GENERATOR ERROR: resource ' . $source . ' is not a ' . Pdf::class . ' class');
        }

        $pdf = (new \ReflectionClass($source))->newInstance();
        $pdf->setData($params);
        $pdf->setRootPath($this->pdfPath);
        $pdf->initiate();
        $pdf->generate();
        $pdf->setTitle($pdf->title());

        $pdf->output($savePath, 'F');
    }

    public function getRessource(string $modelRessource): string
    {
        return $modelRessource;
    }

    public static function getName(): string
    {
        return 'tcpdf';
    }
}
