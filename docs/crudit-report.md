# `crudit_report`: Crudit Report templates

The resource is a [Crudit Report](https://github.com/2lenet/crudit-report) template (`.template.json`) of
`lle_pdf_generator.path`, edited in its visual [designer](crudit-designer.md). The `crudit` command renders it:
template + data → Typst → PDF. Requirements: [crudit and Typst 0.15.0](installation.md#crudit_report-crudit-and-typst), and
`lle_pdf_generator.crudit.enabled: true`.

| | `word_to_pdf` | `crudit_report` |
|---|---|---|
| Template | `.docx`, uploaded | `.template.json`, edited in the designer (or uploaded) |
| Variables | `${client.nom}` | `${client.nom}` and expressions (`${quantite * prix}`, conditions, formats) |
| Lists | `PdfIterable` and repeated table rows | Real tables, groups, repeated sections |
| Images | `${@img[logo]:100x200}` | Image saved in the template, image of the library, or `image` parameter (data-URI, URL, file path) |
| Conversion | PhpWord → unoserver (HTTP) | `crudit render` → Typst (local binaries) |
| PDF/A | — | Produced directly (`output.pdf.standard` of the template) |

## Create a template

In the [admin screen](admin-screen.md): create a template of type `crudit_report` **without file**, it starts from an
empty template (A4); then "Edit in the designer". You can also upload a `.template.json` exported from the designer.

In code: `CruditReportGenerator::createTemplate($dir, $name)` writes an empty template and returns its file name (the
template `path`).

## Data

The data of a template are described by its **parameters** (names, types, formats). There are two ways to define
them:

- **With a [data source](data-sources.md)** (recommended): a PHP class describes the data, the parameters are always
  up to date and read-only in the designer, and the data are extracted according to them;
- **without source**: the parameters are defined in the designer, specific to the template.

Without source, the data set is normalized with the Symfony Serializer (group `pdfgenerator`, or
`CruditReportGenerator::OPTION_GROUPS`), so it must follow the parameters:

```php
$pdfGenerator->generateResponse('INVOICE', [['invoice' => $invoice, 'customer' => $invoice->getCustomer()]]);
```

- `DateTime` values are sent as `YYYY-MM-DD` for the `date` parameters, RFC 3339 otherwise;
- `PdfIterable` values are accepted (the Serializer iterates them), but plain arrays are enough;
- a value of the wrong type (a Doctrine decimal sent as a string to a `number` parameter, for example) is refused
  (error D1): the document is not produced. Data sources convert the values for you.

Without data (`[]` or `[[]]`, the "Show the PDF" action), the template is rendered with its test data.

## Images

The `crudit` command does not read arbitrary paths of the disk. An image can be:

- saved in the template (designer, Resources tab);
- a file of the **library** (`assets/` of `lle_pdf_generator.path`), sent from the designer and shared by all the
  templates: referenced by its path, read by `crudit render -assets`;
- an `image` parameter: a data-URI, a URL of a domain of `lle_pdf_generator.crudit.allowed_hosts`, or, with a data
  source, the path of an image file (converted to a data-URI).

## Errors

A rendering error throws a `RuntimeException` with the diagnostics of `crudit` (for example
`PDF GENERATOR ERROR: crudit render invoice.template.json: E1 el_title: …`).

Installation errors, also `RuntimeException`:

- `crudit` or Typst missing: `PDF GENERATOR ERROR: crudit binary "crudit" not found (lle_pdf_generator.crudit.bin)…`;
- `crudit_report` disabled while a template of this type exists:
  `PDF GENERATOR ERROR: BL is a crudit_report template, which is disabled (lle_pdf_generator.crudit.enabled)`;
- designer not deployed ("Edit in the designer"): `Crudit Report designer not found: public/crudit-designer/index.html`.

## Generator API

| `CruditReportGenerator` | |
|---|---|
| `getVariables($source)` | Paths declared by the template parameters (`lines[].label`) |
| `validate($json)` | `crudit validate`: `['ok' => bool, 'errors' => [...], 'warnings' => [...]]` |
| `createTemplate($dir, $name)` | Empty template; returns its file name |
| `listLibraryFiles($dir)`, `storeLibraryFile(…)` | Library of images and fonts (used by the designer) |
