<?php

namespace Lle\PdfGeneratorBundle\Parsing;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReorganizerTwigParser
{
    public function parse($fileName)
    {
        $finder = new Finder();
        $finder->name($fileName);
        foreach ($finder->in('../templates') as $file) {
            $string = $file->getContents();
        }
        $string = preg_replace('#</table\>\n<p (.+)>{% for (.+) in (.+) %}</p>\n<table>#', "<p>{% for $2 in $3 %}</p>" , $string);
        $string = preg_replace('#</table>\n<p (.+)>{% endfor %}</p>#', "<p>{% endfor %}</p>\n</table>" , $string);
        file_put_contents ( '../templates/'.$fileName , $string);
        return new BinaryFileResponse('../templates/'.$fileName);
    }
}