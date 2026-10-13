# Templates in database

A template (`PdfModelInterface`) has:

| Field | |
|---|---|
| `code` | Identifier used by your code: `generateResponse('INVOICE', …)` |
| `libelle` | Label |
| `type` | `word_to_pdf`, `tcpdf`, `crudit_report`, or your own type; empty: `default_generator`. Several types separated by commas for [several resources](usage.md#several-templates-in-one-document) |
| `path` | Resource: file of `lle_pdf_generator.path` (`.docx`, `.template.json`) or class name (`tcpdf`); several resources separated by commas |
| `datasource` | `crudit_report`: [data source](data-sources.md) of the template, or empty |
| `description` | Free text |
| `file` | Uploaded file (Vich mapping `pdf_model`), stored in `path` |
| `updatedAt` | Date of the last change of the file |

The templates are managed in the [admin screen](admin-screen.md) (with Crudit Bundle), with the
[creation command](commands.md), or in SQL.

## Your own entity

To add fields, use your own class and set `lle_pdf_generator.class`:

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModelTrait;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[ORM\Table(name: 'lle_pdf_model')]
#[ORM\Index(name: 'code_idx', columns: ['code'])]
#[Vich\Uploadable]
class PdfModel implements PdfModelInterface
{
    use PdfModelTrait;
}
```

```yaml
lle_pdf_generator:
    class: 'App\Entity\PdfModel'
```

The creation command does not work with a class that has other required fields.
