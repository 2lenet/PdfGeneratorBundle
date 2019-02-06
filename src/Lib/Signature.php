<?php

namespace Lle\PdfGeneratorBundle\Lib;

use setasign\Fpdi\TcpdfFpdi;

class Signature
{

    private $data;
    private $certificate;
    private $password;

    public function __construct(string $certificate, string $password, array $data = [])
    {
        $this->data = $data;
        $this->password = $password;
        $this->certificate = $certificate;
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


    public function setCertificate(string $certificate): self
    {
        $this->certificate = $certificate;
        return $this;
    }

    public function signe(PdfMerger $pdfMerger): TcpdfFpdi{
        $pdf = $pdfMerger->toTcpdfFpdi();
        $pdf->setSignature('file://'.$this->certificate, 'file://'.$this->certificate, $this->password, '', 2, $this->data , 'A');
        $pdf->setSignatureAppearance(180, 60, 15, 15);
        $pdf->addEmptySignatureAppearance(180, 80, 15, 15);
        return $pdf;
    }




}