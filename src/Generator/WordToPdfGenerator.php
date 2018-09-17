<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;

class WordToPdfGenerator
{
    public static function WordToHtml($source)
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($source);
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save('helloWorld.html');
    }
}