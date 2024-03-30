<?php

namespace Lle\PdfGeneratorBundle\Entity;

use Symfony\Component\HttpFoundation\File\File;

interface PdfModelInterface
{
    public function __toString(): string;

    public function getId(): ?int;

    public function setId(?int $id): self;

    public function getCode(): ?string;

    public function setCode(?string $code): self;

    public function getPath(): ?string;

    public function setPath(?string $path): self;

    public function getDescription(): ?string;

    public function setDescription(?string $description): self;

    public function getLibelle(): ?string;

    public function setLibelle(?string $libelle): self;

    public function getType(): ?string;

    public function setType(?string $type): self;

    public function getFile(): ?File;

    public function setFile(?File $file): self;

    public function getUpdatedAt(): ?\DateTime;

    public function setUpdatedAt(?\DateTime $updatedAt): self;
}
