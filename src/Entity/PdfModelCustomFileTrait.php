<?php
namespace Lle\PdfGeneratorBundle\Entity;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait PdfModelCustomFileTrait{


    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(name="code", type="string", length=50, nullable=false)
     */
    private $code;

    /**
     * @var string|null
     *
     * @ORM\Column(name="path", type="string", length=255, nullable=false)
     */
    private $path;

    /**
     * @var string|null
     *
     * @ORM\Column(name="libelle", type="string", length=255, nullable=false)
     */
    private $libelle;

    /**
     * @var string|null
     *
     * @ORM\Column(name="type", type="string", length=255, nullable=true)
     */
    private $type;

    /**
     * @var string|null
     *
     * @ORM\Column(name="datamodel", type="string", length=255, nullable=true)
     */
    private $datamodel;

    /**
     * @var string|null
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @Vich\UploadableField(mapping="pdf_model", fileNameProperty="path")
     */
    private $file;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    private $updatedAt;


    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @var boolean
     */
    private $checkFile;

    public function __toString(){
        return $this->code . ' '. $this->libelle;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return null|string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param null|string $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return null|string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param null|string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return null|string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param null|string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return null|string
     */
    public function getLibelle()
    {
        return $this->libelle;
    }

    /**
     * @param null|string $libelle
     */
    public function setLibelle($libelle)
    {
        $this->libelle = $libelle;
    }

    /**
     * @return null|string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param null|string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    public function setFile(File $file){
        $this->file = $file;
        if($file){
            $this->setUpdatedAt(new \DateTime('now'));
            $this->checkFile = null;
        }
    }

    public function getFile(){
        return $this->file;
    }


    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }


    public function setUpdatedAt(\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return null|boolean
     */
    public function getCheckFile()
    {
        return $this->checkFile;
    }

    /**
     * @param null|boolean $checkFile
     */
    public function setCheckFile($checkFile)
    {
        $this->checkFile = $checkFile;
    }
    

    /**
     * @return string|null
     */
    public function getDatamodel(): ?string
    {
        return $this->datamodel;
    }

    /**
     * @param string|null $datamodel
     */
    public function setDatamodel(?string $datamodel): void
    {
        $this->datamodel = $datamodel;
    }
        
}
