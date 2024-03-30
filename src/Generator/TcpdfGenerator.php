<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Lle\PdfGeneratorBundle\Lib\Pdf;

class TcpdfGenerator extends AbstractPdfGenerator
{
    public function generate(string $source, iterable $params, string $savePath, array $options = []): void
    {
        $reflex = new \ReflectionClass($source);

        $pdf = $reflex->newInstance();

        if ($pdf instanceof Pdf) {
            $pdf->setData($params);
            $pdf->setRootPath($this->pdfPath);
            $pdf->initiate();
            $pdf->generate();
            $pdf->setTitle($pdf->title());
        } else {
            throw new \Exception('PDF GENERATOR ERROR: ressource ' . $source . ' n\'est pas une class PDF');
        }

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
