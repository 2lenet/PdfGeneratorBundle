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
    private $twig;

    public function __construct(PropertyAccessor $accessor, \Twig\Environment $twig)
    {
        $this->propertyAccess = $accessor;
        $this->twig = $twig;
    }

    private function compile(iterable $params, TemplateProcessor $templateProcessor, array $options)
    {
        $duplicate = [];
        foreach ($templateProcessor->getVariables() as $variable) {
            try {
                [$exp, $root, $var, $img] = $this->getPathVar($variable);
                if (isset($params[$exp[0]]) && $params[$exp[0]] instanceof PdfIterable) {
                    $iterator = $params[$exp[0]];
                    if (!isset($duplicate[$exp[0]])) {
                        $templateProcessor->cloneRow($variable, count($iterator));
                        $duplicate[$exp[0]] = true;
                    }
                    $i = 0;
                    foreach ($iterator as $item) {
                        $this->setVar($templateProcessor, $variable .'#'.++$i, $item, $var , $img);
                    }
                } else {
                    $this->setVar($templateProcessor, $variable, $params, ($var) ? $root . '.' . $var : $root , $img);
                }
            } catch (\Exception $e) {
                if(isset($options[PdfGenerator::OPTION_EMPTY_NOTFOUND_VALUE]) && $options[PdfGenerator::OPTION_EMPTY_NOTFOUND_VALUE]){
                    $templateProcessor->setValue($variable, $params[$variable] ?? '');
                }else{
                    $templateProcessor->setValue($variable, $params[$variable] ?? $variable);
                }
            }
        }
    }

    private function wordToPdf(string $source, iterable $params, string $savePath, array $options)
    {
        $templateProcessor = new TemplateProcessor($source);
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp');
        $this->compile($params, $templateProcessor, $options);
        $templateProcessor->saveAs($tmpFile);

        if($options['twig'] ?? false) {
            $process = new Process(['unoconv', '-o', $tmpFile . '.html', '-f', 'html', $tmpFile]);
            $process->run();
            $template = $this->twig->createTemplate(\file_get_contents($tmpFile . '.html'));
            file_put_contents($tmpFile . '.html.twig', $template->render($params));
            $tmpFile = $tmpFile . '.html.twig';
        }

        $process = new Process(['unoconv','-o',$savePath, '-f', 'pdf', $tmpFile]);
        $process->run();
        if(!$process->isSuccessful()){
            throw new ProcessFailedException($process);
        }
    }

    public function getVariables(string $source):array
    {
        $templateProcessor = new TemplateProcessor($source);
        $res = [];
        foreach ($templateProcessor->getVariables() as $variable) {
            if (array_key_exists($variable, $res)) {
                $res[$variable]++;
            } else {
                $res[$variable] = 1;
            }
        }
        return $res;
    }

    public function generate(string $source, iterable $params, string $savePath, array $options = []):void{
        if(!file_exists($source)){
            if(!file_exists($source.'.docx')) {
                throw new \Exception($source . '(.docx) not found');
            }else{
                $source = $source.'.docx';
            }
        }
        $this->wordToPdf($source, $params, $savePath, $options);
    }

    public static function getName(): string{
        return 'word_to_pdf';
    }

    private function getPathVar($variable){
        if(mb_substr($variable, 0, 4, "UTF-8") === '@img') {
            preg_match('#^@img\[([A-Za-z0-9\.\[\]]+)\](:(\d+)x(\d+))?$#', $variable, $match);
            $variable = $match[1];
        }
        $exp = explode('.', $variable, 2);
        $root = '[' . $exp[0] . ']';
        $var = $exp[1] ?? null;
        return [$exp, $root, $var, $match ?? null];
    }

    private function getImg($root, $var, $match){
        $value = ($var) ? $this->propertyAccess->getValue($root, $var):(string)$root;
        if(substr($value,0,1) === '/'){
            $img = ['path' =>  $value];
        }else{
            $img = ['path' =>  $this->pdfPath.$value];
        }
        if(isset($match[2])){
            $img['width'] = $match[3];
            $img['height'] = $match[4];
        }
        return $img;
    }

    private function getValue($root, $var){
        if($var) {
            $value = $this->propertyAccess->getValue($root, $var);
        }else{
            $value = (string)$root;
        }
        if ($value instanceof \DateTime) {
            $value = $value->format('d/m/Y');
        }
        return htmlspecialchars($value);
    }

    private function setVar($templateProcessor, $variable , $root, $var , $match){
        if(mb_substr($variable, 0, 4, "UTF-8") === '@img'){
            $img = $this->getImg($root, $var, $match);
            $templateProcessor->setImageValue($variable, $img);
        }else {
            $value = $this->getValue($root, $var);
            $templateProcessor->setValue($variable, $value);
        }
    }
}
