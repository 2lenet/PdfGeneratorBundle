<?php

namespace Lle\PdfGeneratorBundle\Converter;

use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use \Lle\PdfGeneratorBundle\Lib\PdfArchive;
use Twig\Environment;

class PdfToPdfArchiveConverter
{
    const ZUGFERD_XML_FILE_NAME = 'zugferd-invoice.xml';

    public function __construct(protected Environment $twig)
    {
    }

    /**
     * Converti un PDF en PDF/A-3B respectant les normes ZUGFeRD
     */
    public function convertToZugferdPdf(string $invoicePdf, string $zugferdXml, array $metadata): string
    {
        $zugferdPdf = new PdfArchive();

        $pageCount = $zugferdPdf->setSourceFile($invoicePdf);

        //recopie les pages du pdf original
        for ($i = 1; $i <= $pageCount; ++$i) {
            $tplIdx = $zugferdPdf->importPage($i, '/MediaBox');

            $zugferdPdf->AddPage();
            $zugferdPdf->useTemplate($tplIdx);
        }

        $xmlStreamReader = StreamReader::createByString($zugferdXml);

        //attache le xml au pdf
        $zugferdPdf->attachStreamReader($xmlStreamReader, self::ZUGFERD_XML_FILE_NAME);

        //ajout des xmp pour la validation PDF/A
        $zugferdPdf->addXMLMetadata($this->prepareZugferdMetadata(array_merge($metadata, [
            'createdAt' => $zugferdPdf->getCreatedAt(),
            'updatedAt' => $zugferdPdf->getCreatedAt(),
            'PDFAPart' => $zugferdPdf->getPart(),
            'PDFAConformance' => $zugferdPdf->getConformance()
        ])));

        //génération du pdf
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp') . '.pdf';

        $zugferdPdf->Output($tmpFile, 'F');

        return $tmpFile;
    }

    protected function prepareZugferdMetadata($metadata): string
    {
        $metadata = [
            'xmlName' => self::ZUGFERD_XML_FILE_NAME,
            'version' => $metadata['version'] ?? '2.0',
            'conformanceLevel' => $metadata['conformanceLevel'] ?? 'BASIC',
            'title' => $metadata['title'] ?? 'zugferd-invoice',
            'description' => $metadata['title'] ?? 'zugferd-invoice',
            'creator' => $metadata['creator'] ?? 'Creator',
            'createdAt' => $metadata['createdAt'] ?? new \DateTime(),
            'updatedAt' => $metadata['updatedAt'] ?? new \DateTime(),
            'PDFAPart' => $metadata['PDFAPart'] ?? '3',
            'PDFAConformance' => $metadata['PDFAConformance'] ?? 'B'
        ];

        return $this->twig->render('@LlePdfGenerator/pdf_archive/zugferd_pdf_xmp.xml.twig', ['metadata' => $metadata]);
    }
}
