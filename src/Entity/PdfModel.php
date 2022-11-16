<?php

namespace Lle\PdfGeneratorBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Table(name: "lle_pdf_model", indexes: [new ORM\Index(name: "idx_code", columns: ["code"])])]
#[ORM\Entity]
#[Vich\Uploadable]
class PdfModel implements PdfModelInterface
{
    use PdfModelTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id;
}