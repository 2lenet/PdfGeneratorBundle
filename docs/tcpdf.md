# `tcpdf`: PHP classes

The resource is a class that extends `Lle\PdfGeneratorBundle\Lib\Pdf` (TCPDF + FPDI). The data set is in
`$this->data`, and `$this->rootPath` is `lle_pdf_generator.path`.

```php
namespace App\Service\Pdf;

use Lle\PdfGeneratorBundle\Lib\Pdf;

class Hello extends Pdf
{
    public function init(): void
    {
        $this->setSourceFile($this->rootPath . 'background.pdf');
    }

    public function myColors(): array
    {
        return ['white' => 'FFFFFF', 'default' => '000000', 'red' => 'FF0000'];
    }

    // fonts in $this->rootPath . 'fonts'
    public function myFonts(): array
    {
        return ['title' => ['size' => 12, 'color' => 'default', 'family' => 'courier', 'style' => 'BU']];
    }

    public function generate(): void
    {
        $this->AddPage('P');
        $this->showGrid(5); // debug: a grid every 5 units
        $this->changeFont('title');
        $this->w(10, 10, 'Hello <b>' . $this->data['name'] . '</b>');
    }
}
```

```php
return $pdfGenerator->generateByRessourceResponse(TcpdfGenerator::getName(), Hello::class, [['name' => 'Ada']]);
```

Or create a template with the resource `App\Service\Pdf\Hello`, type `tcpdf` and code `HELLO`, then
`$pdfGenerator->generateResponse('HELLO', $dataSets)`.
