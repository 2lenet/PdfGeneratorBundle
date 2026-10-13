<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator;

use Lle\PdfGeneratorBundle\DataModel\DataExtractor;
use Lle\PdfGeneratorBundle\DataModel\DataModelRegistry;
use Lle\PdfGeneratorBundle\Exception\ModelNotFoundException;
use Lle\PdfGeneratorBundle\Generator\CruditReportGenerator;
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

    public function testUnknownDataSourceNamesTheTemplate(): void
    {
        $generator = $this->generator();

        $this->expectExceptionMessage('the template BL uses the data source "removed", which does not exist');
        $generator->generate($generator->getRessource('invoice'), [], $this->dir . 'out.pdf', [
            CruditReportGenerator::OPTION_DATASOURCE => 'removed',
            CruditReportGenerator::OPTION_MODEL => 'BL',
        ]);
    }

    /** A failure before rendering (binary missing…) leaves no temporary file. */
    public function testTemporaryFilesAreRemovedOnFailure(): void
    {
        $generator = $this->generator(typst: 'typst-not-installed', models: $this->invoiceModels());
        file_put_contents($this->dir . 'source.template.json', '{"parameters":[]}');
        $before = array_filter(glob(sys_get_temp_dir() . '/crudit*') ?: [], 'is_file');

        try {
            $generator->generate($this->dir . 'source.template.json', [], $this->dir . 'out.pdf', [
                CruditReportGenerator::OPTION_DATASOURCE => 'invoice',
            ]);
            $this->fail('rendered without Typst');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Typst binary', $e->getMessage());
        }

        $this->assertSame($before, array_filter(glob(sys_get_temp_dir() . '/crudit*') ?: [], 'is_file'));
    }

    private function invoiceModels(): DataModelRegistry
    {
        return new DataModelRegistry(
            [new Fixtures\InvoiceDataModel()],
            new ClassMetadataFactory(new AttributeLoader()),
            new DataExtractor(),
            null,
            null,
            new PropertyInfoExtractor([], [new PhpStanExtractor(), new ReflectionExtractor()]),
        );
    }

    public function testDataSource(): void
    {
        $models = $this->invoiceModels();
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

        // a real data set whose values are empty is not "no data": never the sample (data of another document)
        $generator->generate($this->dir . 'source.template.json', ['lines' => []], $out, [CruditReportGenerator::OPTION_DATASOURCE => 'invoice']);
        $this->assertNull(json_decode(explode("---\n", (string)file_get_contents($out))[1], true)['invoice']);

        // without data: the sample of the data source
        $generator->generate($this->dir . 'source.template.json', [], $out, [CruditReportGenerator::OPTION_DATASOURCE => 'invoice']);
        $this->assertSame('EX-1', json_decode(explode("---\n", (string)file_get_contents($out))[1], true)['invoice']['number']);
    }
}
