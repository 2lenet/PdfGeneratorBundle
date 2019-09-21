<?php
namespace Lle\PdfGeneratorBundle\Entity;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait PdfModelTrait{

    use PdfModelCustomFileTrait;
    
    /**
     * @Vich\UploadableField(mapping="pdf_model", fileNameProperty="path")
     */
    private $file;


}