# Configuration reference

```yaml
# config/packages/lle_pdf_generator.yaml (default values)
lle_pdf_generator:
    # folder of the template files, relative to the project
    path: 'data/pdfmodel'
    # type of the templates without type
    default_generator: 'word_to_pdf'
    # template entity (PdfModelInterface)
    class: 'Lle\PdfGeneratorBundle\Entity\PdfModel'
    # word_to_pdf: conversion service
    unoserver: 'http://unoserver/convert'

    # admin screen, added when Crudit Bundle is installed
    screens:
        enabled: true

    # crudit_report
    crudit:
        enabled: false                            # true once crudit, Typst and the designer are installed
        bin: 'crudit'                             # crudit command (PATH by default)
        typst: 'typst'                            # Typst 0.15.0 (PATH by default)
        allowed_hosts: []                         # domains allowed for the images given by URL
        timeout: 90                               # seconds, per document
        designer_url: '/crudit-designer/index.html'
```

| Option | |
|---|---|
| `path` | Folder of the template files (`.docx`, `.template.json`) and of the Crudit Report library (`assets/`, `fonts/`) |
| `default_generator` | Type of a template whose type is empty |
| `class` | Template entity: see [Templates in database](models.md#your-own-entity) |
| `unoserver` | URL of the unoserver conversion service (`word_to_pdf`) |
| `screens.enabled` | `false` to keep your own templates admin screen (see [Admin screen](admin-screen.md#keep-your-own-screen)) |
| `crudit.enabled` | `true` to offer the `crudit_report` type, once the project has installed `crudit`, Typst and the designer (see [Installation](installation.md#crudit_report-crudit-and-typst)). Disabled: no `crudit_report` type, no designer routes, no designer action nor data source field in the admin screen |
| `crudit.bin`, `crudit.typst` | Paths of the `crudit` and `typst` binaries |
| `crudit.allowed_hosts` | Domains from which a template may load an image given by URL (empty: none) |
| `crudit.timeout` | Rendering timeout of one document, in seconds |
| `crudit.designer_url` | URL of the designer `index.html` (see [The Crudit Report designer](crudit-designer.md)) |

Generator options, set in code for the next documents:

```php
$pdfGenerator->addOption(CruditReportGenerator::OPTION_GROUPS, ['invoice']); // crudit_report: Serializer groups
```
