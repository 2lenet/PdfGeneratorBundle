<?php

namespace Lle\PdfGeneratorBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Lle\PdfGeneratorBundle\Repository\PdfModelVersionRepository;

/**
 * A file uploaded for a template (PdfModelInterface), kept in the versions folder (VersionStorage) with its author.
 *
 * The template is referenced by its id, without a foreign key: the import of an archive empties then refills the
 * templates table (ids kept), which a foreign key would block, or empty the history with a cascade.
 */
#[ORM\Entity(repositoryClass: PdfModelVersionRepository::class)]
#[ORM\Table(name: 'lle_pdf_model_version')]
#[ORM\Index(name: 'idx_pdf_model_version_model', columns: ['model_id'])]
class PdfModelVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'model_id', type: 'integer')]
    private ?int $modelId = null;

    /** Code of the template when the file was uploaded. */
    #[ORM\Column(name: 'model_code', type: 'string', length: 50)]
    private ?string $modelCode = null;

    /** Number of the version, from 1 for each template. */
    #[ORM\Column(type: 'integer')]
    private ?int $number = null;

    /** Name of the copy in the versions folder. */
    #[ORM\Column(name: 'stored_name', type: 'string', length: 255)]
    private ?string $storedName = null;

    /** Name of the file on the computer of the author. */
    #[ORM\Column(name: 'original_name', type: 'string', length: 255)]
    private ?string $originalName = null;

    /** Size in bytes. */
    #[ORM\Column(type: 'integer')]
    private ?int $size = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    /** Identifier of the user who uploaded the file (UserInterface::getUserIdentifier()), null when unknown. */
    #[ORM\Column(name: 'created_by', type: 'string', length: 180, nullable: true)]
    private ?string $createdBy = null;

    /** Number of the version restored by this one, null for an upload. */
    #[ORM\Column(name: 'restored_from', type: 'integer', nullable: true)]
    private ?int $restoredFrom = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModelId(): ?int
    {
        return $this->modelId;
    }

    public function setModelId(?int $modelId): self
    {
        $this->modelId = $modelId;

        return $this;
    }

    public function getModelCode(): ?string
    {
        return $this->modelCode;
    }

    public function setModelCode(?string $modelCode): self
    {
        $this->modelCode = $modelCode;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getStoredName(): ?string
    {
        return $this->storedName;
    }

    public function setStoredName(?string $storedName): self
    {
        $this->storedName = $storedName;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getRestoredFrom(): ?int
    {
        return $this->restoredFrom;
    }

    public function setRestoredFrom(?int $restoredFrom): self
    {
        $this->restoredFrom = $restoredFrom;

        return $this;
    }

    public function __toString(): string
    {
        return 'v' . $this->number . ' ' . $this->originalName;
    }
}
