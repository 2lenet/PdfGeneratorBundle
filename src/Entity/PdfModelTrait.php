<?php

namespace Lle\PdfGeneratorBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

trait PdfModelTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id;

    #[ORM\Column(name: "code", type: "string", length: 50, nullable: false)]
    private ?string $code;

    #[ORM\Column(name: "path", type: "string", length: 255, nullable: false)]
    private ?string $path;

    #[ORM\Column(name: "libelle", type: "string", length: 255, nullable: false)]
    private ?string $libelle;

    #[ORM\Column(name: "type", type: "string", length: 255, nullable: true)]
    private ?string $type;

    #[ORM\Column(name: "datamodel", type: "string", length: 255, nullable: true)]
    private ?string $datamodel;

    #[ORM\Column(name: "description", type: "text", nullable: true)]
    private ?string $description;

    #[Vich\UploadableField(mapping: "pdf_model", fileNameProperty: "path")]
    private ?File $file;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTime $updatedAt;

    #[ORM\Column(type: "boolean", nullable: true)]
    private ?bool $checkFile;

    public function __toString(): string
    {
        return $this->code . ' ' . $this->libelle;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): self
    {
        $this->file = $file;

        if ($file) {
            $this->setUpdatedAt(new \DateTime());
            $this->checkFile = null;
        }

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCheckFile(): ?bool
    {
        return $this->checkFile;
    }

    public function setCheckFile(?bool $checkFile): self
    {
        $this->checkFile = $checkFile;

        return $this;
    }

    public function getDatamodel(): ?string
    {
        return $this->datamodel;
    }

    public function setDatamodel(?string $datamodel): self
    {
        $this->datamodel = $datamodel;

        return $this;
    }
}
