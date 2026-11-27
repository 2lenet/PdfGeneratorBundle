<?php

namespace Lle\PdfGeneratorBundle\Tests\Version;

class VersionRecorderTest extends VersionTestCase
{
    public function testUploadRecordsAVersionWithItsAuthorAndACopyOfTheFile(): void
    {
        $recorder = $this->recorder();
        $model = $this->model(7, 'BL');
        $file = $this->templateFile('abc.docx', 'first');

        $recorder->record($model, $file, 'Bon de livraison.docx');
        $recorder->flush($this->entityManager());

        $this->assertCount(1, $this->persisted);
        $version = $this->persisted[0];
        $this->assertSame(7, $version->getModelId());
        $this->assertSame('BL', $version->getModelCode());
        $this->assertSame(1, $version->getNumber());
        $this->assertSame(self::USER, $version->getCreatedBy());
        $this->assertSame('Bon de livraison.docx', $version->getOriginalName());
        $this->assertSame(5, $version->getSize());
        $this->assertNull($version->getRestoredFrom());
        $this->assertSame(1, $this->flushes);

        // the copy survives the deletion of the current file by Vich at the next upload
        unlink($file);
        $this->assertStringEqualsFile($this->storage()->path((string)$version->getStoredName()), 'first');
    }

    public function testNextUploadGetsTheNextNumber(): void
    {
        $recorder = $this->recorder();
        $this->nextNumbers[7] = 3;

        $recorder->record($this->model(7), $this->templateFile('abc.docx', 'third'), 'bl.docx');
        $recorder->flush($this->entityManager());

        $this->assertSame(3, $this->persisted[0]->getNumber());
    }

    public function testTwoUploadsOfTheSameTemplateInOneFlushGetTwoNumbers(): void
    {
        $recorder = $this->recorder();
        $model = $this->model(7);

        $recorder->record($model, $this->templateFile('a.docx', 'a'), 'a.docx');
        $recorder->record($model, $this->templateFile('b.docx', 'b'), 'b.docx');
        $recorder->flush($this->entityManager());

        $this->assertSame([1, 2], array_map(fn ($version): int => (int)$version->getNumber(), $this->persisted));
    }

    public function testIdOfANewTemplateIsReadAtFlush(): void
    {
        $recorder = $this->recorder();
        $model = $this->model(0)->setId(null);

        $recorder->record($model, $this->templateFile('abc.docx', 'new'), 'new.docx');
        $model->setId(12); // id given by the flush of the template
        $recorder->flush($this->entityManager());

        $this->assertSame(12, $this->persisted[0]->getModelId());
    }

    public function testWithoutUserTheAuthorIsUnknown(): void
    {
        $recorder = $this->recorder(null);

        $recorder->record($this->model(7), $this->templateFile('abc.docx', 'cli'), 'cli.docx');
        $recorder->flush($this->entityManager());

        $this->assertNull($this->persisted[0]->getCreatedBy());
    }

    public function testFlushWithoutUploadDoesNothing(): void
    {
        $recorder = $this->recorder();
        $em = $this->entityManager();

        $recorder->record($this->model(7), $this->templateFile('abc.docx', 'once'), 'once.docx');
        $recorder->flush($em);
        $recorder->flush($em); // flush of the versions themselves: nothing left

        $this->assertCount(1, $this->persisted);
        $this->assertSame(1, $this->flushes);
    }
}
