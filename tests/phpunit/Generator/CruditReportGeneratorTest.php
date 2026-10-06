<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator;

use Lle\PdfGeneratorBundle\DataModel\DataExtractor;
use Lle\PdfGeneratorBundle\DataModel\DataModelRegistry;
use Lle\PdfGeneratorBundle\Exception\LibraryFileExistsException;
use Lle\PdfGeneratorBundle\Exception\ModelNotFoundException;
use Lle\PdfGeneratorBundle\Generator\CruditReportGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class CruditReportGeneratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/crudit-test-' . uniqid() . '/';
        mkdir($this->dir);
        file_put_contents($this->dir . 'invoice.template.json', json_encode([
            'parameters' => [
                ['name' => 'number', 'type' => 'string'],
                ['name' => 'issuedOn', 'type' => 'date'],
                ['name' => 'customer', 'type' => 'object', 'children' => [['name' => 'name', 'type' => 'string']]],
                ['name' => 'lines', 'type' => 'array', 'children' => [
                    ['name' => 'label', 'type' => 'string'],
                    ['name' => 'deliveredOn', 'type' => 'date'],
                    ['name' => 'createdAt', 'type' => 'datetime'],
                ]],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    /** Fake crudit: copies the received data (last argument) to the output file (-o). */
    private function generator(
        string $script = 'cp "${@: -1}" "$4"',
        ?DataModelRegistry $models = null,
        ?string $typst = null,
    ): CruditReportGenerator {
        $bin = $this->dir . 'crudit';
        file_put_contents($bin, "#!/bin/bash\n" . $script . "\n");
        chmod($bin, 0o755);

        $generator = new CruditReportGenerator(
            new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()]),
            new ParameterBag([
                'lle.pdf.crudit.bin' => $bin,
                'lle.pdf.crudit.typst' => $typst ?? $bin,
                'lle.pdf.crudit.allowed_hosts' => [],
                'lle.pdf.crudit.timeout' => 10,
            ]),
            $models ?? new DataModelRegistry([], new ClassMetadataFactory(new AttributeLoader()), new DataExtractor()),
        );
        $generator->setPdfPath($this->dir);

        return $generator;
    }

    public function testDataIsNormalizedAndDatesFitTheirType(): void
    {
        $generator = $this->generator();
        $out = $this->dir . 'out.pdf';
        $generator->generate($generator->getRessource('invoice'), [
            'number' => 'F-1',
            'issuedOn' => new \DateTime('2026-10-02 14:30:00'),
            'customer' => ['name' => 'ACME'],
            'lines' => [
                [
                    'label' => 'Cutting',
                    'deliveredOn' => new \DateTime('2026-10-05 08:00:00'),
                    'createdAt' => new \DateTimeImmutable('2026-10-01T10:00:00+02:00'),
                ],
            ],
        ], $out);

        $data = json_decode((string)file_get_contents($out), true);
        $this->assertSame('2026-10-02', $data['issuedOn']);
        $this->assertSame('ACME', $data['customer']['name']);
        $this->assertSame('Cutting', $data['lines'][0]['label']);
        $this->assertSame('2026-10-05', $data['lines'][0]['deliveredOn']);
        $this->assertSame('2026-10-01T10:00:00+02:00', $data['lines'][0]['createdAt']);
    }

    public function testWithoutDataTheTemplateTestDataIsUsed(): void
    {
        $generator = $this->generator('echo -n "$#" > "$4"');
        $out = $this->dir . 'out.pdf';
        $generator->generate($generator->getRessource('invoice'), [], $out);

        // render -json -o out -typst t -assets a -allow h template: no data file
        $this->assertSame('11', file_get_contents($out));
    }

    public function testRenderErrorsAreReported(): void
    {
        $generator = $this->generator(
            'echo \'{"ok":false,"errors":[{"code":"E1","path":"el_title","message":"compilation failed"}]}\'; exit 1'
        );

        $this->expectExceptionMessage('E1 el_title: compilation failed');
        $generator->generate($generator->getRessource('invoice'), [], $this->dir . 'out.pdf');
    }

    public function testMissingBinariesAreReported(): void
    {
        $generator = $this->generator(typst: 'typst-not-installed');

        $this->expectExceptionMessage('Typst binary "typst-not-installed" not found (lle_pdf_generator.crudit.typst)');
        $generator->generate($generator->getRessource('invoice'), [], $this->dir . 'out.pdf');
    }

    public function testMissingModel(): void
    {
        $generator = $this->generator();

        $this->expectException(ModelNotFoundException::class);
        $generator->generate($generator->getRessource('absent'), [], $this->dir . 'out.pdf');
    }

    public function testVariablesComeFromParameters(): void
    {
        $this->assertSame(
            ['number' => 1, 'issuedOn' => 1, 'customer.name' => 1, 'lines[].label' => 1, 'lines[].deliveredOn' => 1, 'lines[].createdAt' => 1],
            $this->generator()->getVariables($this->dir . 'invoice'),
        );
    }

    public function testCreateTemplate(): void
    {
        $fileName = $this->generator()->createTemplate($this->dir, 'Delivery note');

        $this->assertStringEndsWith(CruditReportGenerator::EXTENSION, $fileName);
        $template = json_decode((string)file_get_contents($this->dir . $fileName), true);
        $this->assertSame('Delivery note', $template['name']);
        $this->assertMatchesRegularExpression('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $template['id']);
        $this->assertSame([], $this->generator()->getVariables($this->dir . $fileName));
    }

    public function testStoreLibraryFile(): void
    {
        $generator = $this->generator();
        $png = $this->dir . 'upload';
        // PNG 1×1 transparent
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        $this->assertSame('assets/cafe-logo.png', $generator->storeLibraryFile($this->dir, $png, 'Café Logo.PNG'));
        $this->assertFileEquals($png, $this->dir . 'assets/cafe-logo.png');
        // same content: file reused; other content under the same name: refused, unless overwriting
        $this->assertSame('assets/cafe-logo.png', $generator->storeLibraryFile($this->dir, $png, 'cafe-logo.png'));
        $old = $this->dir . 'assets/cafe-logo.png';
        file_put_contents($old, 'other', FILE_APPEND);
        try {
            $generator->storeLibraryFile($this->dir, $png, 'cafe-logo.png');
            $this->fail('existing file overwritten without confirmation');
        } catch (LibraryFileExistsException $e) {
            $this->assertSame('assets/cafe-logo.png', $e->uri);
        }
        $this->assertSame('assets/cafe-logo.png', $generator->storeLibraryFile($this->dir, $png, 'cafe-logo.png', true));
        $this->assertFileEquals($png, $old);

        $font = $this->dir . 'font';
        file_put_contents($font, "\x00\x01\x00\x00" . str_repeat("\x00", 12));
        $this->assertSame('fonts/inter-bold.ttf', $generator->storeLibraryFile($this->dir, $font, 'Inter-Bold.ttf'));

        $svg = $this->dir . 'svg';
        file_put_contents($svg, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->assertSame('assets/stamp.svg', $generator->storeLibraryFile($this->dir, $svg, 'stamp.svg'));

        // templates, binary and uploaded files (without extension) are not part of the library
        $files = $generator->listLibraryFiles($this->dir);
        $this->assertSame(
            ['assets/cafe-logo.png', 'assets/stamp.svg', 'fonts/inter-bold.ttf'],
            array_column($files, 'uri'),
        );
        $this->assertSame(['image', 'image', 'font'], array_column($files, 'kind'));
        $this->assertSame([1, 1], [$files[0]['widthPx'], $files[0]['heightPx']]);
        $this->assertArrayNotHasKey('widthPx', $files[1]);
        $this->assertSame([], $generator->listLibraryFiles($this->dir . 'absent'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusedLibraryFiles(): iterable
    {
        yield 'extension' => ['<?php echo 1;', 'script.php'];
        yield 'unreadable image' => ['not an image', 'logo.png'];
        yield 'unreadable font' => ['not a font', 'inter.ttf'];
        yield 'unreadable svg' => ['<html></html>', 'stamp.svg'];
    }

    /**
     * @dataProvider refusedLibraryFiles
     */
    #[DataProvider('refusedLibraryFiles')]
    public function testStoreLibraryFileRefusesBadFiles(string $content, string $name): void
    {
        $file = $this->dir . 'upload';
        file_put_contents($file, $content);

        $this->expectException(\InvalidArgumentException::class);
        $this->generator()->storeLibraryFile($this->dir, $file, $name);
    }

    public function testDataSource(): void
    {
        $models = new DataModelRegistry(
            [new Fixtures\InvoiceDataModel()],
            new ClassMetadataFactory(new AttributeLoader()),
            new DataExtractor(),
            null,
            null,
            new PropertyInfoExtractor([], [new PhpStanExtractor(), new ReflectionExtractor()]),
        );
        // fake crudit: copies the template (second to last argument) and the data (last) to the output
        $generator = $this->generator('cat "${@: -2:1}" > "$4"; echo "---" >> "$4"; cat "${@: -1}" >> "$4"', $models);
        file_put_contents($this->dir . 'source.template.json', json_encode([
            'parameters' => [
                ['name' => 'old', 'type' => 'string'],
                ['name' => 'totalWithTax', 'type' => 'number', 'expression' => 'total * 1.2'],
            ],
            'elements' => new \stdClass(),
        ]));
        $out = $this->dir . 'out.pdf';

        $data = new Fixtures\InvoiceData(
            new Fixtures\Invoice('F-1', new \DateTime('2026-10-05T14:30:00+00:00')),
            new \Lle\PdfGeneratorBundle\Lib\PdfIterable([new Fixtures\Line('Cutting')]),
            '12.50',
            new Fixtures\Address('Lyon', '69001'),
        );
        $generator->generate($this->dir . 'source.template.json', get_object_vars($data), $out, [
            CruditReportGenerator::OPTION_DATASOURCE => 'invoice',
        ]);
        [$template, $json] = explode("---\n", (string)file_get_contents($out));
        $this->assertStringContainsString('"elements":{}', str_replace([' ', "\n"], '', $template));
        $template = json_decode($template, true);

        $this->assertSame(
            ['invoice', 'lines', 'total', 'address', 'logo', 'date', 'pageCount', 'totalWithTax'],
            array_column($template['parameters'], 'name'),
        );
        $this->assertSame('invoice', $template['designer']['source']);
        $this->assertSame([
            'invoice' => ['number' => 'F-1', 'issuedOn' => '2026-10-05T14:30:00+00:00'],
            'lines' => [['label' => 'Cutting']],
            'total' => 12.5,
            'address' => ['city' => 'Lyon', 'zip' => '69001'],
            'logo' => null,
            'date' => '',
            'pageCount' => 1,
        ], json_decode($json, true));

        // without data: the sample of the data source
        $generator->generate($this->dir . 'source.template.json', [], $out, [CruditReportGenerator::OPTION_DATASOURCE => 'invoice']);
        $this->assertSame('EX-1', json_decode(explode("---\n", (string)file_get_contents($out))[1], true)['invoice']['number']);
    }
}
