<?php

namespace Lle\PdfGeneratorBundle\Tests\Version;

use Doctrine\Persistence\ObjectRepository;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Lle\PdfGeneratorBundle\Version\VersionInitializer;

class VersionInitializerTest extends VersionTestCase
{
    /** @param list<PdfModel> $models */
    private function initializer(array $models): VersionInitializer
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findAll')->willReturn($models);
        $generator = $this->createMock(PdfGenerator::class);
        $generator->method('getRepository')->willReturn($repository);
        $generator->method('getPath')->willReturn($this->dir);

        return new VersionInitializer($generator, $this->recorder(null), $this->entityManager());
    }

    public function testFirstVersionOfAnExistingFileIsDatedByTheTemplate(): void
    {
        $this->templateFile('bl.docx', 'existing');
        $model = $this->model(7)->setPath('bl.docx')->setUpdatedAt(new \DateTime('2025-03-04 10:20:00'));
        $initializer = $this->initializer([$model]);

        $count = $initializer->init();

        $this->assertSame(1, $count);
        $this->assertCount(1, $this->persisted);
        $version = $this->persisted[0];
        $this->assertSame(1, $version->getNumber());
        $this->assertSame('bl.docx', $version->getOriginalName());
        $this->assertSame('2025-03-04 10:20:00', $version->getCreatedAt()?->format('Y-m-d H:i:s'));
        $this->assertNull($version->getCreatedBy());
        $this->assertStringEqualsFile($this->storage()->path((string)$version->getStoredName()), 'existing');
    }

    public function testTemplatesWithoutASingleFileOrAlreadyVersionedAreSkipped(): void
    {
        $this->templateFile('versioned.docx', 'v');
        $this->templateFile('a.docx', 'a');
        $this->templateFile('b.docx', 'b');
        $this->withVersions = [1];
        $initializer = $this->initializer([
            $this->model(1)->setPath('versioned.docx'),
            $this->model(2)->setPath('App\Pdf\Invoice'),
            $this->model(3)->setPath('a.docx,b.docx'),
            $this->model(4)->setPath('missing.docx'),
            $this->model(5)->setPath('../outside.docx'),
            $this->model(6),
        ]);

        $count = $initializer->init();

        $this->assertSame(0, $count);
        $this->assertSame([], $this->persisted);
        $this->assertSame(0, $this->flushes);
    }
}
