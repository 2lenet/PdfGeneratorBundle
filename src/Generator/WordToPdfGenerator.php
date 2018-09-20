<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Dompdf\Dompdf;

class WordToPdfGenerator
{
    private $twig;

    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    public function wordToPdf($source, $params)
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($source);
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save('../templates/test.html.twig');
        $string = $this->twig->loadTemplate('test.html.twig')->render($params);
        $dompdf = new Dompdf();
        $dompdf->loadHtml($string);
        $dompdf->render();
        $dompdf->stream();
        return new BinaryFileResponse('~/Téléchargements/document.pdf');
    }
}