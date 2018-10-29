<?php

namespace Lle\PdfGeneratorBundle\ObjAccess;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpWord\TemplateProcessor;

class Accessor
{
    public function access($name, $obj, $templateProcessor)
    {
        $arr = get_object_vars($obj);
        foreach ($arr as $key => $content) {
            if (is_object($content) == false && is_array($content) == false) {
                $templateProcessor->setValue($name.'.'.$key, $content);
            }
        }
    }
}