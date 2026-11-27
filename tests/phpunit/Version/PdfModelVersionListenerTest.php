<?php

namespace Lle\PdfGeneratorBundle\Tests\Version;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Lle\PdfGeneratorBundle\EventListener\PdfModelVersionListener;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class PdfModelVersionListenerTest extends VersionTestCase
{
    private function mapping(): PropertyMapping
    {
        $mapping = new PropertyMapping('file', 'path');
        $mapping->setMapping(['upload_destination' => rtrim($this->dir, '/')]);

        return $mapping;
    }

    public function testUploadOfATemplateIsRecordedWithTheClientName(): void
    {
        $listener = new PdfModelVersionListener($this->recorder());
        $file = $this->templateFile('65a1b2.docx', 'uploaded');
        $model = $this->model(3);
        $model->setFile(new UploadedFile($file, 'Bon livraison 2026.docx', null, null, true));
        $mapping = $this->mapping();

        $listener->onPreUpload(new Event($model, $mapping));
        // Vich moves the file, names it, then replaces the UploadedFile by a File (FileInjector)
        $model->setPath('65a1b2.docx')->setFile(new File($file));
        $listener->onPostUpload(new Event($model, $mapping));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));

        $this->assertCount(1, $this->persisted);
        $this->assertSame('Bon livraison 2026.docx', $this->persisted[0]->getOriginalName());
        $this->assertSame(8, $this->persisted[0]->getSize());
    }

    public function testUploadOfAnotherEntityIsIgnored(): void
    {
        $listener = new PdfModelVersionListener($this->recorder());
        $event = new Event(new \stdClass(), $this->mapping());

        $listener->onPostUpload($event);
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));

        $this->assertSame([], $this->persisted);
    }

    /**
     * Vich has already deleted the previous file when the upload is recorded: a failure of the history must not
     * cancel the flush of the template, which would point to a deleted file. It is logged, the upload goes on.
     */
    public function testFailureOfTheHistoryDoesNotStopTheUpload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');
        $listener = new PdfModelVersionListener($this->recorder(), $logger);
        $model = $this->model(3)->setPath('missing.docx');
        $event = new Event($model, $this->mapping());

        $listener->onPostUpload($event);
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));

        $this->assertSame([], $this->persisted);
    }
}
