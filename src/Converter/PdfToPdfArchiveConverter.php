<?php

namespace Lle\PdfGeneratorBundle\Converter;

use Lle\PdfGeneratorBundle\Lib\PdfArchive;
use setasign\Fpdi\PdfParser\StreamReader;
use Twig\Environment;

class PdfToPdfArchiveConverter
{
    const ZUGFERD_XML_FILE_NAME = 'zugferd-invoice.xml';

    public function __construct(protected Environment $twig)
    {
    }

    /**
     * Convert a PDF to PDF/A-3B compliant with ZUGFeRD standards
     */
    public function convertToZugferdPdf(string $invoicePdf, string $zugferdXml, array $metadata): string
    {
        $zugferdPdf = new PdfArchive();

        $pageCount = $zugferdPdf->setSourceFile($invoicePdf);

        // Copy the pages from the original PDF
        for ($i = 1; $i <= $pageCount; ++$i) {
            $tplIdx = $zugferdPdf->importPage($i, '/MediaBox');

            $zugferdPdf->AddPage();
            $zugferdPdf->useTemplate($tplIdx);
        }

        $xmlStreamReader = StreamReader::createByString($zugferdXml);

        // Attach the XML to the PDF
        $zugferdPdf->attachStreamReader($xmlStreamReader, self::ZUGFERD_XML_FILE_NAME);

        // XML added for PDF/A validation
        $zugferdPdf->addXMLMetadata(
            $this->prepareZugferdMetadata(
                array_merge($metadata, [
                    'createdAt' => $zugferdPdf->getCreatedAt(),
                    'updatedAt' => $zugferdPdf->getCreatedAt(),
                    'PDFAPart' => $zugferdPdf->getPart(),
                    'PDFAConformance' => $zugferdPdf->getConformance(),
                ])
            )
        );

        // PDF generation
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp') . '.pdf';

        $zugferdPdf->Output($tmpFile, 'F');

        return $tmpFile;
    }

    protected function prepareZugferdMetadata(array $metadata): string
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
            'PDFAConformance' => $metadata['PDFAConformance'] ?? 'B',
        ];

        return $this->twig->render('@LlePdfGenerator/pdf_archive/zugferd_pdf_xmp.xml.twig', ['metadata' => $metadata]);
    }
}
