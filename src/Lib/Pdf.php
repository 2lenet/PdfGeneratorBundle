<?php

namespace Lle\PdfGeneratorBundle\Lib;

use setasign\Fpdi\Tcpdf\Fpdi;

abstract class Pdf extends Fpdi
{
    protected bool $debug = false;

    protected array $data;

    protected string $rootPath;

    public function generate(): void
    {
    }

    public function myColors(): ?array
    {
        return [];
    }

    public function myFonts(): ?array
    {
        return [];
    }

    protected function init(): void
    {
    }

    public function initiate(): void
    {
        $this->init();
    }

    public function setRootPath(string $path): self
    {
        $this->rootPath = $path;

        return $this;
    }

    protected function log(string $str): void
    {
        if ($this->debug) {
            echo $str . '<br/>';
        }
    }

    protected function colors(mixed $c): array
    {
        $colors = $this->myColors();

        if (is_array($colors) && isset($colors[$c])) {
            return $this->hexaToArrayColor($colors[$c]);
        }

        if ($c == 'default') {
            return $this->hexaToArrayColor('000000');
        }

        if ($c == 'strong') {
            return $this->hexaToArrayColor('000000');
        }

        return $this->hexaToArrayColor(str_replace("#", "", $c));
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function title(): string
    {
        return '';
    }

    public function header(): void
    {
    }

    public function footer(): void
    {
        $h = $this->getPageHeight();
        $w = $this->getPageWidth();

        $this->rectangle($w, 9, 0, $h - 9, 'default');

        $this->changeFont('default');

        $this->w($w - 5, 6, '', ['w' => $w, 'h' => $h - 9, 'align' => 'R']); // Unknown $this->getPage() method
    }

    public function nombreDePageSur(string $pdf): false|int
    {
        if (false !== ($file = file_get_contents($pdf))) {
            $pages = preg_match_all("/\/Page\W/", $file, $matches);

            return $pages;
        }

        return false;
    }

    protected function hexaToArrayColor(string $color): array
    {
        $red = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue = hexdec(substr($color, 4, 2));

        return ['R' => $red, 'G' => $green, 'B' => $blue];
    }

    protected function drawImage(
        string $file,
        float $x = 0,
        float $y = 0,
        ?float $width = null,
        ?float $height = null,
        array $options = [],
    ): bool {
        $round = isset($options['round']) && $options['round'];
        $crop = isset($options['crop']) && $options['crop'];
        $center = isset($options['center']) && $options['center'];

        $palign = '';
        $align = 'N';

        $resize = 0;
        $dpi = 300;

        if ($file && @fopen($file, 'r')) {
            $size = @getimagesize($file);

            if ($size) {
                $width = ($width) ?: $size[0];
                $height = ($height) ?: $size[1];

                if (!$crop) {
                    $size = $this->redimenssion($size[0], $size[1], $width, $height);

                    $w = $size[0];
                    $h = $size[1];

                    if ($center) {
                        $x += $width / 2 - $w / 2;
                        $y += $height / 2 - $h / 2;
                    }
                } else {
                    $w = $width;
                    $h = $height;

                    $target = __DIR__ . '/../../../../web/media/pdf/' . md5($file);

                    $size = getimagesize($file);

                    $i = new \Imagick($file);
                    $i->cropThumbnailImage((int)($width * 72 / 25.4), (int)($height * 72 / 25.4));

                    if ($round) {
                        $background = new \Imagick();
                        $background->newImage($size[0], $size[1], new \ImagickPixel('white'));

                        $i->roundCornersImage(500, 500);
                        $i->compositeImage($background, \imagick::COMPOSITE_DSTATOP, 0, 0);
                    }

                    $i->writeImage($target);

                    $file = $target;
                }

                $this->Image(
                    $file,
                    $x,
                    $y,
                    $w,
                    $h,
                    '',
                    '',
                    $align,
                    $resize,
                    $dpi,
                    $palign,
                );
            }

            return true;
        } else {
            $this->log('ERROR:' . $file . ' NOT VALIDE');

            return false;
        }
    }

    public function drawCircle(float $x, float $y, float $r, mixed $c): void
    {
        $this->circle($x, $y, $r, 0, 360, 'F', [], $this->colors($c), 2);
    }

    public function changeFontFamily(string $police): void
    {
        $data = $this->rootPath . '/fonts';

        $this->SetFont($police);
    }

    protected function w(?float $x, ?float $y, string $html, array $options = []): void
    {
        $w = (isset($options['w'])) ? $options['w'] : 0;
        $h = (isset($options['h'])) ? $options['h'] : 0;

        $align = (isset($options['align'])) ? $options['align'] : 'C';

        $this->writeHTMLCell($w, $h, $x, $y, $html, 0, 0, false, true, $align, true);
    }

    protected function writeInRect(
        float $w,
        float $h,
        ?float $x,
        ?float $y,
        string $html,
        string $align,
        array $c,
        bool $moveX = false,
    ): void {
        $this->rectangle($w, $h, $x, $y, $c);

        $this->w($x, $y, $html, ['w' => $w, 'h' => $h, 'align' => $align]);
    }

    protected function changeColor(mixed $c): void
    {
        $this->SetTextColorArray($this->colors($c));
    }

    protected function changeFont(mixed $f): void
    {
        $fonts = $this->myFonts();

        $f = $fonts[$f] ?? ['size' => 9, 'color' => 'default', 'family' => 'helvetica'];

        if (isset($f['size'])) {
            $this->SetFontSize($f['size']);
        }

        if (isset($f['color'])) {
            $this->changeColor($f['color']);
        }

        if (isset($f['family'])) {
            $this->changeFontFamily($f['family']);
        }

        if (isset($f['style'])) {
            $this->changeFontStyle($f['style']);
        }
    }

    protected function changeFontStyle(string $style): void
    {
        $this->FontStyle = $style;
    }

    protected function rectangle(float $w, float $h, float $x, float $y, mixed $c): void
    {
        $this->Rect($x, $y, $w, $h, 'F', ['width' => 0], $this->colors($c));
    }

    protected function rectangleEmpty(float $w, float $h, float $x, float $y, mixed $c): void
    {
        $border_style = ['all' => ['width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 1, 'phase' => 0]];

        $this->Rect($x, $y, $w, $h, 'D', $border_style, $this->colors($c));
    }

    protected function traceHLine(float $y, array $options = []): void
    {
        $w = $this->getPageWidth();

        $color = (isset($options['color'])) ? $options['color'] : 'default';
        $weight = (isset($options['weight'])) ? $options['weight'] : 0.5;

        $x = (isset($options['start'])) ? $options['start'] : 0;
        $w = ((isset($options['end'])) ? $options['end'] : $w) - $x;

        $this->rectangle($w, $weight, $x, $y, $color);
    }

    protected function traceVline(float $x, array $options = []): void
    {
        $h = $this->getPageHeight();

        $weight = (isset($options['weight'])) ? $options['weight'] : 0.5;
        $color = (isset($options['color'])) ? $options['color'] : 'default';

        $this->rectangle($weight, $h, $x, 0, $color);
    }

    protected function square(float $size, float $x, float $y, mixed $c): void
    {
        $this->rectangle($size, $size, $x, $y, $c);
    }

    protected function redimenssion(
        float $originalWidth,
        float $originalHeight,
        float $targetWidth,
        float $targetHeight,
    ): array {
        while ($originalWidth > $targetWidth || $originalHeight > $targetHeight) {
            $ratio = 1 / ($originalWidth / $targetWidth);

            if ($ratio >= 1) {
                $ratio = 0.99;
            }

            $originalWidth = $originalWidth * $ratio;
            $originalHeight = $originalHeight * $ratio;
        }

        return [$originalWidth, $originalHeight];
    }

    protected function showGrid(int $size = 5): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->traceHLine($i * $size, ['weight' => ($i % 5) ? 0.2 : 0.4, 'color' => ($i % 5) ? 'default' : 'strong']);
            $this->traceVLine($i * $size, ['weight' => ($i % 5) ? 0.2 : 0.4, 'color' => ($i % 5) ? 'default' : 'strong']);
        }
    }
}
