<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Response;

class WordToPdfGenerator
{
    public static function wordToPdf($source)
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($source);
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save('test.html');
        shell_exec("wkhtmltopdf test.html test");
        return new Response("done");
    }
}