<?php

namespace Lle\PdfGeneratorBundle\Lib;

use setasign\Fpdi\Tcpdf\Fpdi;

class Signature
{
    private ?array $bgColor = null;

    private ?array $sgColor = null;

    public function __construct(
        private ?string $certificate = null,
        private ?string $password = null,
        private array $data = [],
        private ?string $image = null,
        private ?array $position = null,
    ) {
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

    public function setName(string $name): self
    {
        $this->data['Name'] = $name;

        return $this;
    }

    public function setLocation(string $location): self
    {
        $this->data['Location'] = $location;

        return $this;
    }

    public function setReason(string $reason): self
    {
        $this->data['Reason'] = $reason;

        return $this;
    }

    public function setContactInfo(string $contactInfo): self
    {
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

    public function setSegments(array $segments, ?array $position = null): self
    {
        $pointsPolygones = [];

        foreach ($segments as $segment) {
            $points = [];

            foreach ($segment as $line) {
                $points[] = $line[0];
                $points[] = $line[1];
            }

            $pointsPolygones[] = $points;
        }

        return $this->setPoints($pointsPolygones, $position);
    }

    public function setPoints(array $pointsPolygones, ?array $position = null): self
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp');
        $image = imagecreate(200, 100);

        $bg = $this->bgColor
            ? imagecolorallocatealpha($image, ...$this->bgColor)
            : imagecolorallocate($image, 255, 255, 255);

        $sg = $this->sgColor
            ? imagecolorallocatealpha($image, ...$this->sgColor)
            : imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, 249, 249, $bg);

        foreach ($pointsPolygones as $points) {
            for ($i = 0; $i < count($points) - 2; $i = $i + 2) {
                imageline($image, $points[$i], $points[$i + 1], $points[$i + 2], $points[$i + 3], $sg);
            }
        }

        imagepng($image, $tmpFile);

        return $this->setImage($tmpFile, $position);
    }

    public function setImage(string $image, ?array $position = null): self
    {
        $this->image = $image;

        return $this->setPosition($position ?? $this->position);
    }

    public function setPosition(?array $position = null): self
    {
        $this->position = $position ?? [];

        return $this;
    }

    public function setCertificate(string $certificate): self
    {
        $this->certificate = $certificate;

        return $this;
    }

    public function signe(PdfMerger $pdfMerger): Fpdi
    {
        $pdf = $pdfMerger->toTcpdfFpdi();

        return $this->signeTcpdfFpdi($pdf);
    }

    public function signeTcpdfFpdi(Fpdi $pdf): Fpdi
    {
        if ($this->image) {
            $s = $this->position['s'] ?? 8;
            $w = $this->position['w'] ?? 40;
            $h = $this->position['h'] ?? 20;
            $x = $this->position['x'] ?? $pdf->getPageWidth() - $w;
            $y = $this->position['y'] ?? $pdf->getPageHeight() - ($h * 2 + 5);

            if (isset($this->position['p']) && $this->position['p']) {
                $pdf->setPage($this->position['p']);
            }

            $pdf->SetAutoPageBreak(false, PDF_MARGIN_BOTTOM);
            $pdf->Image($this->image, $x, $y, $w, $h, 'PNG');
            $pdf->SetFontSize($s);
            $pdf->SetFillColor(255, 255, 255);

            if (isset($this->position['header'])) {
                $spx = $pdf->getStringHeight($w, $this->position['header']);
                $pdf->SetXY($x, $y - $spx);
                $pdf->Cell($w, $spx, $this->position['header'], 0, 0, 'C', true);
            }

            if (isset($this->position['footer'])) {
                $spx = $pdf->getStringHeight($w, $this->position['footer']);
                $pdf->SetXY($x, $y + $h);
                $pdf->Cell($w, $spx, $this->position['footer'], 0, 0, 'C', true);
            }

            $pdf->setPage($pdf->getNumPages());
            $pdf->SetAutoPageBreak(false);
        }

        if ($this->certificate && $this->password) {
            $pdf->setSignature(
                'file://' . $this->certificate,
                'file://' . $this->certificate,
                $this->password,
                '',
                2,
                $this->data,
                'A'
            );

            if ($this->image) {
                $pdf->setSignatureAppearance($x, $y, $w, $h);
            }
        }

        return $pdf;
    }

    public function getBgColor(): ?array
    {
        return $this->bgColor;
    }

    /**
     * Call this **BEFORE** setting points/segments
     * Allows to set the background color (default opaque white)
     */
    public function setBgColor(int $red, int $green, int $blue, ?int $alpha = 0): self
    {
        $this->bgColor = [$red, $green, $blue, $alpha];

        return $this;
    }

    public function getSgColor(): ?array
    {
        return $this->sgColor;
    }

    /**
     * Call this **BEFORE** setting points/segments
     * Allows to set the signature color (default opaque black)
     */
    public function setSgColor(int $red, int $green, int $blue, ?int $alpha = 0): self
    {
        $this->sgColor = [$red, $green, $blue, $alpha];

        return $this;
    }
}
