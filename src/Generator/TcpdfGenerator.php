<?php

namespace Lle\PdfGeneratorBundle\Generator;

use App\Service\Pdf\Pdf417;
use Lle\PdfGeneratorBundle\Lib\Pdf;
use Lle\PdfGeneratorBundle\ObjAccess\Accessor;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class TcpdfGenerator extends AbstractPdfGenerator
{
    public function generate(string $source, iterable $params, string $savePath, array $options = []): void
    {
        $reflex = new \ReflectionClass($source);

        $pdf = $reflex->newInstance();

        if ($pdf instanceof Pdf) {
            $pdf->setData($params);
            $pdf->setRootPath($this->pdfPath);
            $pdf->initiate();
            $pdf->generate();
            $pdf->setTitle($pdf->title());
        } else {
            throw new \Exception('PDF GENERATOR ERROR: ressource ' . $source . ' n\'est pas une class PDF');
        }

        $pdf->output($savePath, 'F');
    }

    public function getRessource(string $modelRessource): string
    {
        return $modelRessource;
    }

    public static function getName(): string
    {
        return 'tcpdf';
    }
}
