<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Finder\Finder;
use Dompdf\Dompdf;

class WordToPdfGenerator
{
    public static function wordToPdf($source)
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($source);
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save('test.html');
        $finder = new Finder();
        $finder->in('./')->name('test.html');
        foreach ($finder as $file) {
            $contents = $file->getContents();
        }
        $dompdf = new Dompdf();
        $dompdf->loadHtml($contents);
        $dompdf->render();
        $dompdf->stream();
        return new BinaryFileResponse('~/Téléchargements/document.pdf');
    }
}