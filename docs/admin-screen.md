# Admin screen (with Crudit Bundle)

When [Crudit Bundle](https://github.com/2lenet/CruditBundle) is installed, the bundle adds the templates screen, with
nothing to write in the project:

- list (filters on label and code), show, new, edit, delete;
- a `crudit_report` template created without file starts from an empty template, to edit in the designer;
- "Data source" field for `crudit_report` templates (see [Data sources](data-sources.md));
- actions "Edit in the designer" (`crudit_report` templates), "Download the template", "Show the PDF" (rendered
  without data: test data, or the sample of the data source);
- list actions "Export the templates" and "Import templates" (see [Copy the templates](transfer.md)).

The "Data source" field and the "Edit in the designer" action appear only when `crudit.enabled` is `true`.

Without Crudit Bundle, nothing of it is loaded. Translations: domain `PdfGeneratorBundle` (fr, en).

## Menu

Crudit attaches a menu entry to a parent of the same menu provider only: the entry stays in the project.

```php
use Lle\PdfGeneratorBundle\Crudit\Config\PdfModelCrudConfig;

LinkElement::new('menu.pdfmodel', Path::new(PdfModelCrudConfig::ROOT_ROUTE . '_index'), Icon::new('file-pdf'))
    ->setRole('ROLE_PDFMODEL_INDEX'),
```

## Roles

The screen is named `PDFMODEL`. All the routes of the bundle are under `/pdfmodel/` and check a role
(`Lle\PdfGeneratorBundle\Security\PdfModelRoles`):

| Role | Routes |
|---|---|
| `ROLE_PDFMODEL_INDEX`, `_SHOW`, `_NEW`, `_EDIT`, `_DELETE` | Screen `lle_pdfgenerator_crudit_pdfmodel_*`: `/pdfmodel/`, `show/{id}`, `new`, `edit/{id}`, `delete/{id}` |
| `ROLE_PDFMODEL_SHOW` | `lle_pdf_generator_download_model` (GET `/pdfmodel/download/{id}`), `lle_pdf_generator_show_model` (GET `/pdfmodel/pdf/{id}`) |
| `ROLE_PDFMODEL_DESIGNER` | Designer routes (see [The Crudit Report designer](crudit-designer.md#routes)) |
| `ROLE_PDFMODEL_EXPORT` / `ROLE_PDFMODEL_IMPORT` | `lle_pdf_generator_models_export` / `_import` (GET / POST `/pdfmodel/archive`) |

With [CredentialBundle](https://github.com/2lenet/CredentialBundle), `bin/console lle:credential:warmup` registers
them; give them to the groups. Without it, add them to the role hierarchy of the project.

## Keep your own screen

```yaml
lle_pdf_generator:
    screens:
        enabled: false
```
