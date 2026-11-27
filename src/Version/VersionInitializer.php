<?php

namespace Lle\PdfGeneratorBundle\Version;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelVersion;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Lle\PdfGeneratorBundle\Repository\PdfModelVersionRepository;

/**
 * Starts the history of the templates uploaded before it: version 1 of each template that has a single file of the
 * templates folder and no version yet, dated by the template (updatedAt), author unknown. Can be run again.
 */
class VersionInitializer
{
    public function __construct(
        protected PdfGenerator $pdfGenerator,
        protected VersionRecorder $recorder,
        protected EntityManagerInterface $em,
    ) {
    }

    /** Returns the number of versions created. */
    public function init(): int
    {
        /** @var PdfModelVersionRepository $versions */
        $versions = $this->em->getRepository(PdfModelVersion::class);
        $dir = rtrim($this->pdfGenerator->getPath(), '/');

        $count = 0;
        foreach ($this->pdfGenerator->getRepository()->findAll() as $model) {
            if (!$model instanceof PdfModelInterface || $model->getId() === null || $versions->hasVersions($model->getId())) {
                continue;
            }
            // a single file of the templates folder (not a tcpdf class, nor several resources)
            $path = (string)$model->getPath();
            if (str_contains($path, ',') || !PdfGenerator::isRelativePath($path) || !is_file($dir . '/' . $path)) {
                continue;
            }

            $updatedAt = $model->getUpdatedAt();
            $this->recorder->record(
                $model,
                $dir . '/' . $path,
                basename($path),
                $updatedAt ? \DateTimeImmutable::createFromMutable($updatedAt) : null,
            );
            $count++;
        }
        $this->recorder->flush($this->em);

        return $count;
    }
}
