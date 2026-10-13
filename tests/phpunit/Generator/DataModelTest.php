<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator;

use Lle\PdfGeneratorBundle\DataModel\DataExtractor;
use Lle\PdfGeneratorBundle\DataModel\DataModelBuilder;
use Lle\PdfGeneratorBundle\Tests\Generator\Fixtures\InvoiceData;
use Lle\PdfGeneratorBundle\Tests\Generator\Fixtures\Untyped;
use Lle\PdfGeneratorBundle\Tests\Generator\Fixtures\UntypedList;
use Lle\PdfGeneratorBundle\Tests\Generator\Fixtures\WithoutField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

class DataModelTest extends TestCase
{
    private function builder(): DataModelBuilder
    {
        return new DataModelBuilder(
            new ClassMetadataFactory(new AttributeLoader()),
            null,
            null,
            new PropertyInfoExtractor([], [new PhpStanExtractor(), new ReflectionExtractor()]),
        );
    }

    public function testTheDataClassDescribesTheParameters(): void
    {
        $this->assertSame([
            ['name' => 'invoice', 'type' => 'object', 'children' => [
                ['name' => 'number', 'type' => 'string'],
                ['name' => 'issuedOn', 'type' => 'datetime'],
            ]],
            ['name' => 'lines', 'type' => 'array', 'children' => [['name' => 'label', 'type' => 'string']]],
            ['name' => 'total', 'type' => 'number'],
            ['name' => 'address', 'type' => 'object', 'children' => [
                ['name' => 'city', 'type' => 'string'],
                ['name' => 'zip', 'type' => 'string'],
            ]],
            ['name' => 'logo', 'type' => 'image'],
            ['name' => 'date', 'type' => 'string', 'label' => 'Current date'],
            ['name' => 'pageCount', 'type' => 'integer'],
        ], $this->builder()->describe(InvoiceData::class));
    }

    public function testBadDataClasses(): void
    {
        foreach ([Untyped::class => 'PHP type required', UntypedList::class => 'unknown item type', WithoutField::class => 'has no public property'] as $class => $message) {
            try {
                $this->builder()->describe($class);
                $this->fail($class . ' accepted');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testExtractConvertsValues(): void
    {
        $png = tempnam(sys_get_temp_dir(), 'png');
        imagepng(imagecreatetruecolor(1, 1), $png);
        $parameters = [
            ['name' => 'n', 'type' => 'number'], ['name' => 'i', 'type' => 'integer'], ['name' => 'b', 'type' => 'boolean'],
            ['name' => 'd', 'type' => 'date'], ['name' => 's', 'type' => 'string'], ['name' => 'img', 'type' => 'image'],
            ['name' => 'missing', 'type' => 'string'], ['name' => 'empty', 'type' => 'array', 'children' => []],
            ['name' => 'address', 'type' => 'object', 'children' => [['name' => 'city', 'type' => 'string']]],
            ['name' => 'calc', 'type' => 'number', 'expression' => 'n * 2'],
        ];
        $data = [
            'n' => '12.50', 'i' => '3', 'b' => 1, 'd' => new \DateTimeImmutable('2026-10-05 10:00'), 's' => 42,
            'img' => $png, 'empty' => null, 'address' => (object)['city' => 'Lyon'],
        ];

        $out = json_decode((string)json_encode((new DataExtractor())->extract($parameters, $data)), true);
        unlink($png);

        $this->assertSame(12.5, $out['n']);
        $this->assertSame(3, $out['i']);
        $this->assertTrue($out['b']);
        $this->assertSame('2026-10-05', $out['d']);
        $this->assertSame('42', $out['s']);
        $this->assertStringStartsWith('data:image/png;base64,', $out['img']);
        $this->assertNull($out['missing']);
        $this->assertSame([], $out['empty']);
        $this->assertSame(['city' => 'Lyon'], $out['address']);
        $this->assertArrayNotHasKey('calc', $out);
    }
}
