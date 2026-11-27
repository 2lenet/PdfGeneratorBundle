<?php

namespace Lle\PdfGeneratorBundle\Version;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelVersion;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;

/**
 * Puts a version back as the current file of its template: the copy becomes a new file of the templates folder, the
 * old current file is deleted (it has its own copy in the versions folder) and a new version records the restoration.
 */
class VersionRestorer
{
    public function __construct(
        protected VersionStorage $storage,
        protected VersionRecorder $recorder,
        protected EntityManagerInterface $em,
    ) {
    }

    public function restore(PdfModelInterface $model, PdfModelVersion $version): void
    {
        if ($model->getId() === null || $version->getModelId() !== $model->getId()) {
            throw new \InvalidArgumentException('The version ' . $version->getId() . ' is not a version of the template ' . $model->getCode());
        }

        $copy = $this->storage->path((string)$version->getStoredName());
        if (!is_file($copy)) {
            throw new \RuntimeException('File of the version ' . $version->getNumber() . ' of the template ' . $model->getCode() . ' not found');
        }

        $dir = $this->storage->templatesDir();
        $extension = pathinfo($copy, PATHINFO_EXTENSION);
        $name = bin2hex(random_bytes(12)) . ($extension !== '' ? '.' . $extension : '');
        if (!@copy($copy, $dir . '/' . $name)) {
            throw new \RuntimeException('Cannot copy the version ' . $version->getNumber() . ' to the templates folder');
        }

        $current = (string)$model->getPath();
        $model->setPath($name)->setUpdatedAt(new \DateTime());
        $this->recorder->recordRestore($model, $version);
        $this->em->flush();

        // a single file of the templates folder only (not a tcpdf class, nor several resources, nor a path leaving it)
        if ($current !== '' && !str_contains($current, ',') && PdfGenerator::isRelativePath($current) && is_file($dir . '/' . $current)) {
            unlink($dir . '/' . $current);
        }
    }
}
