<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator;

use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Lle\PdfGeneratorBundle\Generator\TcpdfGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PdfGeneratorTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function paths(): iterable
    {
        yield 'file' => ['bl.docx', true];
        yield 'subfolder' => ['bl/2026.template.json', true];
        yield 'tcpdf class' => ['App\Service\Pdf\Invitation', true];
        yield 'dots in a name' => ['bl..v2.docx', true];
        yield 'parent' => ['../../.env', false];
        yield 'parent inside' => ['bl/../../.env', false];
        yield 'parent with backslashes' => ['..\..\.env', false];
        yield 'absolute' => ['/etc/passwd', false];
        yield 'windows absolute' => ['C:\secret', false];
        yield 'empty' => ['', false];
    }

    /**
     * @dataProvider paths
     */
    #[DataProvider('paths')]
    public function testIsRelativePath(string $path, bool $expected): void
    {
        $this->assertSame($expected, PdfGenerator::isRelativePath($path));
    }

    /** The class named by a tcpdf template is checked before being instantiated. */
    public function testTcpdfRefusesAnotherClass(): void
    {
        try {
            (new TcpdfGenerator())->generate(Fixtures\NotAPdf::class, [], sys_get_temp_dir() . '/out.pdf');
            $this->fail('class accepted');
        } catch (\Exception $e) {
            $this->assertStringContainsString('PDF GENERATOR ERROR', $e->getMessage());
        }

        $this->assertFalse(Fixtures\NotAPdf::$created);
    }
}
