<?php

namespace Lle\PdfGeneratorBundle\Lib;

use setasign\Fpdi\TcpdfFpdi;
use \PDFMerger as Base;

class PdfMerger extends Base
{
    public function toTcpdfFpdi(): TcpdfFpdi
    {
        $pdf = new TcpdfFpdi();

        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp');
        $this->merge('file', $tmpFile);
        $page = $pdf->setSourceFile($tmpFile);

        for ($p = 1; $p <= $page; $p++) {
            $pdf->AddPage('P');
            $pdf->useImportedPage($pdf->importPage($p));
        }

        return $pdf;
    }
}
