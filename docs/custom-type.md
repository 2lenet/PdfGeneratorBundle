# Create your own type

A type is a service that implements `Lle\PdfGeneratorBundle\Generator\PdfGeneratorInterface` (autoconfigured with the
tag `lle.pdf.generator`), usually by extending `AbstractPdfGenerator`:

```php
use Lle\PdfGeneratorBundle\Generator\AbstractPdfGenerator;

class HtmlGenerator extends AbstractPdfGenerator
{
    public static function getName(): string
    {
        return 'html'; // value of PdfModel::type
    }

    /**
     * Writes the PDF of the resource $source with the data set $params to $savePath.
     * $options: options of PdfGenerator::addOption() (and the data source of the template, for crudit_report).
     */
    public function generate(string $source, iterable $params, string $savePath, array $options = []): void
    {
        // …
    }

    // Optional: resource from the template path (default: lle_pdf_generator.path + path)
    public function getRessource(string $modelRessource): string
    {
        return $this->pdfPath . $modelRessource;
    }
}
```

The type is then proposed in the "Type" field of the [admin screen](admin-screen.md).

To read variables like `word_to_pdf`, use the same convention: the first part of a variable is the key of the data
set (`[first].rest` with the PropertyAccessor).
