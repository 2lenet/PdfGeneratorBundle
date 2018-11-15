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
    public function access($name, $obj, $templateProcessor, $iteration)
    {
        $class = get_class($obj);
        $arr = (array) $obj;
        if ($iteration >= 4) {
            return;
        }
        foreach ($arr as $key => $content) {
            $keyExploded = explode($class, $key);
            if (count($keyExploded) == 2) {
                if (is_object($content) == false && is_array($content) == false) {
                    $templateProcessor->setValue($name.'.'.trim($keyExploded[1]), $content);
                } elseif (is_object($content) == true) {
                    dd($content);
                    $this->access($name.'.'.trim($keyExploded[1]), $content, $templateProcessor, $iteration + 1);
                }
            }
        }
    }
}