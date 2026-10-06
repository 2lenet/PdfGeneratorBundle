# Data sources

A `crudit_report` template can get its data structure from the application: a **data source**. The data are an
object of a dedicated class whose **typed public properties describe the template parameters**. It is the single
source of truth: the code that builds the data cannot add a value without declaring it, and PHP and static analysis
check the types.

| | With a data source | Without source |
|---|---|---|
| Choice | "Data source" field of the template (`PdfModel::datasource`) | Empty (default) |
| Parameters | Described by the data class, read-only in the designer, always up to date | Defined in the designer, specific to the template |
| In the designer | Computed parameters can be added | Everything |
| Test data | Sample of the source: the same data as a real document, read at each opening | Typed in the designer |
| Rendering | Current parameters of the source; data **extracted according to them** | Data normalized by the Serializer |

## Declare a source

1. The **data class**:

```php
namespace App\Service\Bl;

use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditFields;
use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditImage;
use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditLabel;

final class BlData
{
    public function __construct(
        public Commande $commande,                       // entity: fields of the Serializer group pdfgenerator
        #[CruditLabel('Addresses')]
        public BlAdresses $adresses,                     // data class: its typed public properties
        /** @var iterable<Bl> */
        #[CruditFields(['nobl', 'datebl', 'qte'])]       // entity without group: given fields
        public iterable $bls,
        public string $date,
        #[CruditImage]
        public string $qrCode,                           // data-URI, URL or image file path
    ) {
    }

    /** What PdfGenerator receives. */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

2. The code that builds the data returns it, and gives `toArray()` to the generator (the array is the same for a
`word_to_pdf` template):

```php
public function getData(Commande $commande): BlData
{
    return new BlData(commande: $commande, adresses: $this->getAdresses($commande), /* … */);
}

return $this->pdfGenerator->generateResponse('BL', [$this->getData($commande)->toArray()]);
```

3. The **source**, a service that implements `CruditDataModelInterface` (registered automatically):

```php
use Lle\PdfGeneratorBundle\DataModel\CruditDataModelInterface;

class BlDataModel implements CruditDataModelInterface
{
    public static function getName(): string { return 'bl'; }            // PdfModel::datasource
    public function getLabel(): string { return 'Delivery note'; }        // admin screen
    public function getDataClass(): string { return BlData::class; }

    /** Sample for the designer preview and "Show the PDF": the same code as a real document. */
    public function getSample(): ?BlData
    {
        $commande = $this->commandeRepository->findLast();

        return $commande ? $this->blGenerator->getData($commande) : null;
    }
}
```

4. Choose the source in the template form ("Data source").

## Data class

Each public non-static property is a parameter, in the order of declaration:

| Property type | Parameter |
|---|---|
| `string`, `int`, `float`, `bool` | text, integer, number, boolean |
| `DateTimeInterface` | date and time (`#[CruditType('date')]` for the day only) |
| enum | text |
| entity (Doctrine mapping or Serializer group) | object: fields of the Serializer group `pdfgenerator`, or those of `#[CruditFields]` |
| other class | object, described the same way (its typed public properties) |
| `array`, `iterable`, `Collection` | list; item class read in the PHPDoc (`@var list<Bl>`, `iterable<Bl>`, `Bl[]`) |

Attributes (`Lle\PdfGeneratorBundle\DataModel\Attribute`):

| Attribute | |
|---|---|
| `#[CruditImage]` | Image: data-URI, URL, or path of an image file |
| `#[CruditType('date')]` | Forced type: `string`, `number`, `integer`, `boolean`, `date`, `datetime`, `image` |
| `#[CruditFields(['nobl', …])]` | Fields of an entity or a list of entities, instead of the Serializer group |
| `#[CruditLabel('…')]` | Label in the designer (default: the `field.<name>` translation of the project) |

Fields of an entity:

- types from the Doctrine mapping (`decimal` and `float` → number, `date` → date, `datetime` → date and time,
  `integer` → integer, `boolean` → boolean), otherwise from the PHP type of the property or getter;
- relations are followed up to 3 levels, without coming back to a class already visited (cycles); a relation
  without exported field is ignored;
- labels: the `field.<name>` translations of the project (Crudit convention).

A property without type, or a list without item class, is an error that names it
(`BlData::$bls: unknown item type, to be given in PHPDoc`). The item class is read by the Symfony
PropertyInfo component (`property_info` service), which needs `phpstan/phpdoc-parser` (installed with most Symfony
projects). `phpdocumentor/reflection-docblock` alone reads `list<Class>` but not `iterable<Class>`.

## Rendering

For a template with a source, the generator:

1. replaces the parameters of the template with the current ones of the source (the computed parameters of the
   template are kept): a change of the PHP cannot break a template that was not reopened;
2. **extracts** each parameter from the data set (array key, property or getter, with the PropertyAccessor) and
   converts it: numeric strings (Doctrine decimals) to numbers, `DateTime` to `YYYY-MM-DD` or RFC 3339, missing value
   to `null`, missing list to `[]`, image file path to a data-URI. The data always match the parameters; the
   Serializer groups are not used;
3. without data (`[[]]`, "Show the PDF"), renders the sample of the source.

A template whose data source no longer exists (renamed or removed in the PHP) cannot be rendered:
`PDF GENERATOR ERROR: the template BL uses the data source "bl", which does not exist…`. Choose another source in the
templates screen.

A field removed from the data class leaves the expressions that use it empty (warning W4 in the designer): reopen the
templates of the source after such a change.

## In the designer

When the template is read, the bundle puts the parameters of the source and its sample (`testData`) in it, with
`designer.source`; the designer shows the parameters with a lock, read-only, and only offers "+ Computed parameter".
A sample that cannot be produced (empty database…) does not prevent opening the template. When saving, the provided
parameters are put back from the source, whatever the designer sends.

**Personal data**: the sample is shown to every user who has `ROLE_PDFMODEL_DESIGNER` or `ROLE_PDFMODEL_SHOW`
("Show the PDF"). Taken from the database (the last order…), it holds real customer data: give these roles
accordingly, or build a sample with fictitious data.
