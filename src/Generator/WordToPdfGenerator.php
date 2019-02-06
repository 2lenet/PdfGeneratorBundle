<?php

namespace Lle\PdfGeneratorBundle\Generator;

use setasign\Fpdi\TcpdfFpdi;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Finder\Finder;
use Dompdf\Dompdf;
use Lle\PdfGeneratorBundle\ObjAccess\Accessor;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Lle\PdfGeneratorBundle\Lib\PdfMerger;

class WordToPdfGenerator extends AbstractPdfGenerator
{

    private $twig;
    private $propertyAccess;

    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    private function handleTable($params, $templateProcessor) {
        for ($i = 1; $i <= count($params[PdfGenerator::ITERABLE]); $i++) {
            foreach ($params[PdfGenerator::ITERABLE]['table' . $i][0] as $key => $content) {
                $clonekey = $key;
            }
            $templateProcessor->cloneRow($clonekey, count($params[PdfGenerator::ITERABLE]['table' . $i]));
            foreach ($params[PdfGenerator::ITERABLE] as $table) {
                $k = 0;
                foreach($table as $var) {
                    $k++;
                    foreach ($var as $key => $content) {
                        $templateProcessor->setValue($key . '#' . $k, $content);
                    }
                }
            }
        }
    }

    private function handleVars($params, TemplateProcessor $templateProcessor) {
        foreach($templateProcessor->getVariables() as $variable){
            try {
                $v = $this->twig->createTemplate('{{' . $variable . '}}')->render($params[PdfGenerator::VARS]);
                $templateProcessor->setValue($variable, $v);
            }catch(\Exception $e){
                $templateProcessor->setValue($variable, $params[PdfGenerator::VARS][$variable] ?? $variable);
            }
        }
    }

    private function wordToPdf($source, $params, $savePath)
    {
        $templateProcessor = new TemplateProcessor($source);
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp');
        if (array_key_exists(PdfGenerator::ITERABLE, $params)  ) {
            $this->handleTable($params, $templateProcessor);
        }
        if (array_key_exists(PdfGenerator::VARS, $params)) {
            if (array_key_exists(PdfGenerator::ITERABLE, $params)) {
                $this->handleVars($params, $templateProcessor);
            }
        }
        $templateProcessor->saveAs($tmpFile);
        $process = new Process(['unoconv','-o',$savePath, '-f', 'pdf', $tmpFile]);
        $process->run();
        if(!$process->isSuccessful()){
            throw new ProcessFailedException($process);
        };
    }

    public function generate(string $source, iterable $params, string $savePath):void{
        if(!file_exists($source)){
            if(!file_exists($source.'.docx')) {
                throw new \Exception($source . '(.docx) not found');
            }else{
                $source = $source.'.docx';
            }
        }
        $this->wordToPdf($source, $params, $savePath);
    }

    public static function getName(): string{
        return 'word_to_pdf';
    }
}