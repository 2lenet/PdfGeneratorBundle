<?php

namespace Lle\PdfGeneratorBundle\Converter;

use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use \Lle\PdfGeneratorBundle\Lib\PdfArchive;
use Twig\Environment;

class PdfToPdfArchiveConverter
{
    const ZUGFERD_XML_FILE_NAME = "zugferd-invoice.xml";

    /** @var Environment $twig */
    protected $twig;
    
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Converti un PDF en PDF/A-3b respectant les normes ZUGFeRD
     *
     * @param string $invoicePdf chemin vers le pdf
     * @param string $zugferdXml xml de la facture
     * @param array $metadata
     * @return string chemin vers le pdf généré
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
        $zugferdPdf->addXMLMetadata($this->prepareXMLMetadata(array_merge($metadata, [
            'createdAt' => $zugferdPdf->getFormattedCreatedAt(),
            'updatedAt' => $zugferdPdf->getFormattedCreatedAt()
        ])));
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp').'.pdf';
        $zugferdPdf->Output($tmpFile,'F');
        return $tmpFile;
    }

    protected function prepareXMLMetadata($metadata): string
    {
        $metadata = [
            "xmlName" => self::ZUGFERD_XML_FILE_NAME,
            "version" => $metadata["version"] ?? "2.0",
            "conformanceLevel" => $metadata["conformanceLevel"] ?? "BASIC",
            "title" => $metadata["title"] ?? "zugferd-invoice",
            "description" => $metadata["title"] ?? "Description",
            "creator" => $metadata["creator"] ?? "Creator",
            "creator" => $metadata["description"] ?? "Description",
            "createdAt" => $metadata["createdAt"] ?? "",
            "updatedAt" => $metadata["updatedAt"] ?? "",
        ];
        return $this->twig->render('@LlePdfGenerator/pdf_archive/zugferd_pdf_xmp.xml.twig', ["metadata" => $metadata]);
    }
}
