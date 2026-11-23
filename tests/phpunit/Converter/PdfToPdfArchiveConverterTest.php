<?php

namespace Lle\PdfGeneratorBundle\Tests\Converter;

use Lle\PdfGeneratorBundle\Converter\PdfToPdfArchiveConverter;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** PDF/A-3 with an attached XML: name and XMP metadata of the attachment. */
class PdfToPdfArchiveConverterTest extends TestCase
{
    private const XML = '<?xml version="1.0" encoding="UTF-8"?><rsm:CrossIndustryInvoice/>';

    public function testAttachedXmlAndXmpMetadata(): void
    {
        $pdf = $this->convert(['title' => 'F-1', 'creator' => 'Test']);

        $this->assertStringContainsString('(zugferd-invoice.xml)', $pdf);
        $this->assertStringContainsString('<fx:DocumentFileName>zugferd-invoice.xml</fx:DocumentFileName>', $pdf);
        $this->assertStringContainsString('<fx:Version>2.0</fx:Version>', $pdf);
        $this->assertStringContainsString('<fx:ConformanceLevel>BASIC</fx:ConformanceLevel>', $pdf);
        // PDF/A-3 6.6.2.3.1: the namespace URI holds on one line
        $this->assertStringContainsString('<pdfaSchema:namespaceURI>urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#</pdfaSchema:namespaceURI>', $pdf);
    }

    /** @param array<string, string> $metadata */
    private function convert(array $metadata): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../src/Resources/views', 'LlePdfGenerator');
        $source = tempnam(sys_get_temp_dir(), 'pdf') . '.pdf';
        $fpdi = new Fpdi();
        $fpdi->AddPage();
        $fpdi->Output('F', $source);

        $result = (new PdfToPdfArchiveConverter(new Environment($loader)))->convertToZugferdPdf($source, self::XML, $metadata);
        $content = (string)file_get_contents($result);
        unlink($source);
        unlink($result);

        return $content;
    }
}
