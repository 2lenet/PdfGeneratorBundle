<?php

namespace Lle\PdfGeneratorBundle\Tests\Version;

use Lle\PdfGeneratorBundle\Entity\PdfModelVersion;
use Lle\PdfGeneratorBundle\Version\VersionRecorder;
use Lle\PdfGeneratorBundle\Version\VersionRestorer;

class VersionRestorerTest extends VersionTestCase
{
    private function restorer(VersionRecorder $recorder): VersionRestorer
    {
        return new VersionRestorer($this->storage(), $recorder, $this->entityManager());
    }

    private function version(int $modelId, int $number, string $content): PdfModelVersion
    {
        $file = $this->templateFile('upload-' . $number . '.docx', $content);
        $version = (new PdfModelVersion())
            ->setModelId($modelId)
            ->setNumber($number)
            ->setStoredName($this->storage()->store($file))
            ->setOriginalName('bl-v' . $number . '.docx')
            ->setSize(strlen($content));
        unlink($file);

        return $version;
    }

    public function testRestorePutsTheVersionBackAsCurrentFileAndRecordsIt(): void
    {
        $recorder = $this->recorder();
        $restorer = $this->restorer($recorder);
        $version = $this->version(7, 1, 'first');
        $this->templateFile('current.docx', 'second');
        $model = $this->model(7)->setPath('current.docx');
        $this->nextNumbers[7] = 3;

        $restorer->restore($model, $version);
        $recorder->flush($this->entityManager()); // done by the listener after the flush of the template

        $this->assertNotSame('current.docx', $model->getPath());
        $this->assertStringEndsWith('.docx', (string)$model->getPath());
        $this->assertStringEqualsFile($this->dir . $model->getPath(), 'first');
        $this->assertFileDoesNotExist($this->dir . 'current.docx', 'its copy is in the versions folder');
        $this->assertNotNull($model->getUpdatedAt());

        $this->assertCount(1, $this->persisted);
        $restored = $this->persisted[0];
        $this->assertSame(3, $restored->getNumber());
        $this->assertSame(1, $restored->getRestoredFrom());
        $this->assertSame($version->getStoredName(), $restored->getStoredName());
        $this->assertSame('bl-v1.docx', $restored->getOriginalName());
        $this->assertSame(self::USER, $restored->getCreatedBy());
    }

    public function testRestoreOfAVersionOfAnotherTemplateIsRefused(): void
    {
        $restorer = $this->restorer($this->recorder());
        $version = $this->version(8, 1, 'other');
        $this->templateFile('current.docx', 'current');
        $model = $this->model(7)->setPath('current.docx');

        $caught = null;
        try {
            $restorer->restore($model, $version);
        } catch (\InvalidArgumentException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught);
        $this->assertSame('current.docx', $model->getPath());
        $this->assertFileExists($this->dir . 'current.docx');
    }

    public function testRestoreKeepsACurrentPathOutsideTheTemplatesFolder(): void
    {
        $recorder = $this->recorder();
        $restorer = $this->restorer($recorder);
        $version = $this->version(7, 1, 'first');
        $outside = $this->templateFile('outside.docx', 'outside');
        $model = $this->model(7)->setPath('../' . basename(rtrim($this->dir, '/')) . '/outside.docx');

        $restorer->restore($model, $version);

        $this->assertFileExists($outside, 'a path leaving the templates folder is never deleted');
    }
}
