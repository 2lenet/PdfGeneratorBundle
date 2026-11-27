<?php

namespace Lle\PdfGeneratorBundle\Version;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelVersion;
use Lle\PdfGeneratorBundle\Repository\PdfModelVersionRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * History of the files of the templates. A file is copied as soon as it is recorded; the version is saved at the next
 * flush (flush()), once the template has an id: Vich uploads during the flush of the template, where a new entity
 * cannot be persisted.
 */
class VersionRecorder implements ResetInterface
{
    /** @var list<array{model: PdfModelInterface, version: PdfModelVersion}> */
    private array $pending = [];

    public function __construct(
        protected VersionStorage $storage,
        protected ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    /**
     * A new file of a template ($file, already in the templates folder), uploaded as $originalName, now unless
     * $createdAt is given (file uploaded before the history).
     */
    public function record(PdfModelInterface $model, string $file, string $originalName, ?\DateTimeImmutable $createdAt = null): void
    {
        $version = (new PdfModelVersion())
            ->setStoredName($this->storage->store($file))
            ->setOriginalName($originalName)
            ->setSize((int)filesize($file));

        $this->add($model, $version, $createdAt);
    }

    /** A version put back as the current file of its template: new version sharing the stored copy. */
    public function recordRestore(PdfModelInterface $model, PdfModelVersion $restored): void
    {
        $version = (new PdfModelVersion())
            ->setStoredName($restored->getStoredName())
            ->setOriginalName($restored->getOriginalName())
            ->setSize($restored->getSize())
            ->setRestoredFrom($restored->getNumber());

        $this->add($model, $version);
    }

    /** Saves the recorded versions; does nothing when there is none (flush of the versions themselves). */
    public function flush(EntityManagerInterface $em): void
    {
        if ($this->pending === []) {
            return;
        }
        $pending = $this->pending;
        $this->pending = [];

        /** @var PdfModelVersionRepository $repository */
        $repository = $em->getRepository(PdfModelVersion::class);
        $numbers = [];
        foreach ($pending as ['model' => $model, 'version' => $version]) {
            $modelId = (int)$model->getId();
            $numbers[$modelId] ??= $repository->nextNumber($modelId);
            $version
                ->setModelId($modelId)
                ->setModelCode($model->getCode())
                ->setNumber($numbers[$modelId]++);
            $em->persist($version);
        }
        $em->flush();
    }

    /** Between two requests of a long-running process: versions of a request whose flush failed are dropped. */
    public function reset(): void
    {
        $this->pending = [];
    }

    private function add(PdfModelInterface $model, PdfModelVersion $version, ?\DateTimeImmutable $createdAt = null): void
    {
        $version
            ->setCreatedAt($createdAt ?? new \DateTimeImmutable())
            ->setCreatedBy($this->tokenStorage?->getToken()?->getUserIdentifier());

        $this->pending[] = ['model' => $model, 'version' => $version];
    }
}
