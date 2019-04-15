<?php
namespace Lle\PdfGeneratorBundle\Entity;

use Symfony\Component\HttpFoundation\File\File;

interface PdfModelInterface{

    public function __toString();
    public function getId(): ?int;
    public function setId(int $id);
    public function getCode();
    public function setCode($code);
    public function getPath();
    public function setPath($path);
    public function getDescription();
    public function setDescription($description);
    public function getLibelle();
    public function setLibelle($libelle);
    public function getType();
    public function setType($type);
    public function setFile(File $file);
    public function getFile();
    public function getUpdatedAt(): \DateTime;
    public function setUpdatedAt(\DateTime $updatedAt);
}