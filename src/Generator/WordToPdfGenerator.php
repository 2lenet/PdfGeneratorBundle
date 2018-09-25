<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Lle\PdfGeneratorBundle\Parsing\ReorganizerTwigParser;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Finder\Finder;
use Dompdf\Dompdf;

class WordToPdfGenerator
{

    const ITERABLE = 'iterable';
    const VARS = 'vars';
    private $twig;
    private $reorganizerTwigParser;

    public function __construct(\Twig_Environment $twig, ReorganizerTwigParser $reorganizerTwigParser)
    {
        $this->twig = $twig;
        $this->reorganizerTwigParser = $reorganizerTwigParser;
    }

    public function handleTable($params) {
        $templateProcessor = new TemplateProcessor('Template.docx');
        for ($i = 1; $i <= count($params[self::ITERABLE]); $i++) {
            foreach ($params[self::ITERABLE]['table' . $i][0] as $key => $content) {
                $clonekey = $key;
            }
            $templateProcessor->cloneRow($clonekey, count($params[self::ITERABLE]['table' . $i]));
            foreach ($params[self::ITERABLE] as $table) {
                $k = 0;
                foreach($table as $var) {
                    $k++;
                    foreach ($var as $key => $content) {
                        $templateProcessor->setValue($key . '#' . $k, $content);
                    }
                }
            }
        }
        $templateProcessor->saveAs('TemplateTest.docx');
    }

    public function handleVars($params, $docname) {
        $templateProcessor = new TemplateProcessor($docname);
        foreach ($params[self::VARS] as $key => $content) {
            $templateProcessor->setValue($key, $content);
        }
        $templateProcessor->saveAs('TemplateTest.docx');
    }

    public function wordToPdf($source, $params)
    {
        if (array_key_exists(self::ITERABLE, $params)  ) {
            $this->handleTable($params);
        }
        if (array_key_exists(self::VARS, $params)) {
            if (array_key_exists(self::ITERABLE, $params)) {
                $this->handleVars($params, 'TemplateTest.docx');
            } else {
                $this->handleVars($params, 'Template.docx');
            }
        }
        $phpWord = \PhpOffice\PhpWord\IOFactory::load('TemplateTest.docx');
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save('../templates/TemplateTest.html');
        $finder = new Finder();
        $finder->name('TemplateTest.html');
        foreach ($finder->in('../templates') as $file) {
            $string = $file->getContents();
        }
        $dompdf = new Dompdf();
        $dompdf->loadHtml($string);
        $dompdf->render();
        $dompdf->stream();
        return new BinaryFileResponse('~/Téléchargements/document.pdf');
    }
}