<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Lle\PdfGeneratorBundle\Lib\PdfIterable;
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

    private $propertyAccess;

    public function __construct(PropertyAccessor $accessor)
    {
        $this->propertyAccess = $accessor;
    }

    private function compile(iterable $params, TemplateProcessor $templateProcessor)
    {
        $duplicate = [];
        foreach ($templateProcessor->getVariables() as $variable) {
            try {
                $exp = explode('.', $variable, 2);
                $root = '[' . $exp[0] . ']';
                $var = $exp[1] ?? null;
                if (isset($params[$exp[0]]) && $params[$exp[0]] instanceof PdfIterable) {
                    $iterator = $params[$exp[0]];
                    if (!isset($duplicate[$exp[0]])) {
                        $templateProcessor->cloneRow($variable, count($iterator));
                        $duplicate[$exp[0]] = true;
                    }
                    $i = 0;
                    foreach ($iterator as $item) {
                        $i++;
                        if($var){
                            $templateProcessor->setValue($exp[0] . '.' . $var . '#' . $i, $this->propertyAccess->getValue($item, $var));
                        }else{
                            $templateProcessor->setValue($exp[0] .'#' . $i, (string)$item);
                        }
                    }
                } else {
                    $varPath = ($var) ? $root . '.' . $var : $root;
                    $value = $this->propertyAccess->getValue($params, $varPath);
                    $templateProcessor->setValue($variable, $value);
                }
            } catch (\Exception $e) {
                dd($e);
                dd($variable, $params[$variable]);
                $templateProcessor->setValue($variable, $params[$variable] ?? $variable);
            }
        }
    }

    private function wordToPdf(string $source, iterable $params, string $savePath)
    {
        $templateProcessor = new TemplateProcessor($source);
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp');
        $this->compile($params, $templateProcessor);
        $templateProcessor->saveAs($tmpFile);
        $process = new Process(['unoconv','-o',$savePath, '-f', 'pdf', $tmpFile]);
        $process->run();
        if(!$process->isSuccessful()){
            throw new ProcessFailedException($process);
        }
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