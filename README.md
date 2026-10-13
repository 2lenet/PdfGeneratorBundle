<h1 align="center">PdfGeneratorBundle</h1>

<p align="center">
    <a href="https://github.com/2lenet/PdfGeneratorBundle/actions" target="_blank">
        <img src="https://github.com/2lenet/PdfGeneratorBundle/actions/workflows/phpstan.yml/badge.svg" alt="PHPStan status">
    </a>
    <a href="https://github.com/2lenet/PdfGeneratorBundle/actions" target="_blank">
        <img src="https://github.com/2lenet/PdfGeneratorBundle/actions/workflows/phpunit.yml/badge.svg" alt="PHPUnit status">
    </a>
    <a href="https://github.com/2lenet/PdfGeneratorBundle/actions" target="_blank">
        <img src="https://github.com/2lenet/PdfGeneratorBundle/actions/workflows/validate.yml/badge.svg" alt="PHPCS status">
    </a>
</p>

Generate PDF documents from templates stored in your application, and manage those templates.

A template (`PdfModel`) has a code, a type and a resource. Your code asks for a document by code and gives the
data; the bundle renders it with the generator of the template type:

| Type | Template | Rendering |
|---|---|---|
| `word_to_pdf` | `.docx` file with `${variables}` | PhpWord, then [unoserver](https://github.com/unoconv/unoserver) |
| `tcpdf` | PHP class (TCPDF + FPDI) | TCPDF |
| `crudit_report` | [Crudit Report](https://github.com/2lenet/crudit-report) template (`.template.json`), edited in its visual designer | `crudit` command (template → Typst → PDF) |

```php
return $pdfGenerator->generateResponse('INVOICE', [['invoice' => $invoice]]);
```

With [Crudit Bundle](https://github.com/2lenet/CruditBundle), the bundle also provides the templates admin screen:
upload, Crudit Report designer, preview, copy of the templates from one platform to another.

## Installation

```bash
composer require 2lenet/pdf-generator-bundle
```

Afterwards, visit the [documentation](docs/index.md) to set up and use PdfGeneratorBundle.

Upgrading? See the [upgrade guide](docs/upgrade.md).

## Licence

PdfGeneratorBundle is released under the terms of the [MIT License](LICENSE).
