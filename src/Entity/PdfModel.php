<?php

namespace Lle\PdfGeneratorBundle\Entity;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Doctrine\ORM\Mapping as ORM;


/**
 *
 * @ORM\Table(name="lle_pdf_model", indexes={@ORM\Index(name="idx_code", columns={"code"})})
 * @ORM\Entity
 * @Vich\Uploadable
 */
class PdfModel implements PdfModelInterface
{
    use PdfModelTrait;
}
