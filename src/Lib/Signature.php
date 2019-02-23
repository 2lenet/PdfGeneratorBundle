<?php

namespace Lle\PdfGeneratorBundle\Lib;

use setasign\Fpdi\TcpdfFpdi;

class Signature
{

    private $data;
    private $certificate;
    private $password;
    private $image = null;
    private $position = [];

    public function __construct(string $certificate = null, string $password = null, array $data = [], ?string $image = null, ?array $position = null)
    {
        $this->data = $data;
        $this->password = $password;
        $this->certificate = $certificate;
        $this->image = $image;
        $this->position = $position;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function setName(string $name): self{
        $this->data['Name'] = $name;
        return $this;
    }

    public function setLocation(string $location): self{
        $this->data['Location'] = $location;
        return $this;
    }

    public function setReason(string $reason): self{
        $this->data['Reason'] = $reason;
        return $this;
    }

    public function setContactInfo(string $contactInfo): self{
        $this->data['ContactInfo'] = $contactInfo;
        return $this;
    }


    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getCertificate(): string
    {
        return $this->certificate;
    }

    public function setSegments(array $segments, ?array $position = null): self{
        $points = [];
        foreach($segments as $line){
            $points[] = $line[0];
            $points[] = $line[1];
        }
        return $this->setPoints($points, $position);
    }

    public function setPoints(array $points, ?array $position = null): self{
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp');
        $image = imagecreate(200,100);
        $bg   = imagecolorallocate($image, 255, 255, 255);
        $sg = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, 249, 249, $bg);
        imagePolygon($image, $points, count($points)/2, $sg);
        imagepng($image, $tmpFile);
        return $this->setImage($tmpFile, $position);
    }

    public function setImage(string $image, ?array $position = null): self{
        $this->image = $image;
        return $this->setPosition($position ?? $this->position);
    }

    public function setPosition(?array $position = null): self{
        $this->position = $position ?? [];
        return $this;
    }


    public function setCertificate(string $certificate): self
    {
        $this->certificate = $certificate;
        return $this;
    }

    public function signe(PdfMerger $pdfMerger): TcpdfFpdi{
        $pdf = $pdfMerger->toTcpdfFpdi();
        return $this->signeTcpdfFpdi($pdf);
    }

    public function signeTcpdfFpdi(TcpdfFpdi $pdf): TcpdfFpdi{
        if($this->certificate && $this->password) {
            $pdf->setSignature('file://' . $this->certificate, 'file://' . $this->certificate, $this->password, '', 2, $this->data, 'A');
        }
        if($this->image) {
            $w = $this->position['w'] ?? 40;
            $h = $this->position['h'] ?? 20;
            $x = $this->position['x'] ?? $pdf->getPageWidth() - $w;
            $y = $this->position['y'] ?? $pdf->getPageHeight() - ($h * 2 + 5);
            if(isset($this->position['p']) && $this->position['p']){
                $pdf->setPage($this->position['p']);
            }
            $pdf->Image($this->image, $x, $y, $w, $h, 'PNG');
            $pdf->setSignatureAppearance($x, $y, $w, $h);
            $pdf->addEmptySignatureAppearance($x, $y, $w, $h);
            $pdf->setPage($pdf->getNumPages());
        }
        return $pdf;
    }




}