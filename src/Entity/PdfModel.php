<?php

namespace Lle\PdfGeneratorBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ORM\Table(name="lle_pdf_model", indexes={@ORM\Index(name="idx_code", columns={"code"})})
 * @ORM\Entity
 * @UniqueEntity(fields="code")
 * @Vich\Uploadable
 */
class PdfModel implements PdfModelInterface
{
    use PdfModelTrait;
}
