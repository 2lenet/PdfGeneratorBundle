<?php

namespace Lle\PdfGeneratorBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events as DoctrineEvents;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Version\VersionRecorder;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events as VichEvents;

/**
 * Records a version at each upload of a template file (Vich), saved after the flush of the template.
 *
 * A failure of the history is logged and does not stop the upload: Vich has already deleted the previous file, so
 * cancelling the flush would leave the template pointing to a deleted file.
 */
#[AsEventListener(event: VichEvents::PRE_UPLOAD, method: 'onPreUpload')]
#[AsEventListener(event: VichEvents::POST_UPLOAD, method: 'onPostUpload')]
#[AsDoctrineListener(event: DoctrineEvents::postFlush)]
class PdfModelVersionListener
{
    /** @var \WeakMap<PdfModelInterface, string> name of the uploaded file, read before Vich replaces the UploadedFile */
    private \WeakMap $originalNames;

    public function __construct(
        protected VersionRecorder $recorder,
        protected ?LoggerInterface $logger = null,
    ) {
        $this->originalNames = new \WeakMap();
    }

    public function onPreUpload(Event $event): void
    {
        $model = $event->getObject();
        $uploaded = $event->getMapping()->getFile($model);
        if ($model instanceof PdfModelInterface && $uploaded instanceof UploadedFile) {
            $this->originalNames[$model] = $uploaded->getClientOriginalName();
        }
    }

    public function onPostUpload(Event $event): void
    {
        $model = $event->getObject();
        if (!$model instanceof PdfModelInterface) {
            return;
        }

        $mapping = $event->getMapping();
        $dir = $mapping->getUploadDir($model);
        $file = $mapping->getUploadDestination() . '/' . ($dir ? $dir . '/' : '') . $mapping->getFileName($model);
        $originalName = $this->originalNames[$model] ?? basename($file);
        unset($this->originalNames[$model]);

        try {
            $this->recorder->record($model, $file, $originalName);
        } catch (\Throwable $e) {
            $this->logger?->error('PDF template ' . $model->getCode() . ': file uploaded without its version: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        try {
            $this->recorder->flush($args->getObjectManager());
        } catch (\Throwable $e) {
            $this->logger?->error('PDF templates: versions not saved: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
