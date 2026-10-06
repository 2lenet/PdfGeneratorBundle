# Installation

## Bundle

```bash
composer require 2lenet/pdf-generator-bundle
```

Import the bundle routes (`config/routes/lle_pdf_generator.yaml`):

```yaml
lle_pdf_generator:
    resource: "@LlePdfGeneratorBundle/Resources/config/routes.yaml"
    prefix: /
```

All the routes are under `/pdfmodel/` and check a role (see [Admin screen](admin-screen.md#roles)): `/pdfmodel/` must be
covered by the firewall of the admin users.

## Database

The templates are stored in the `lle_pdf_model` table (`Lle\PdfGeneratorBundle\Entity\PdfModel`, or your own class:
see [Templates in database](models.md)). Generate and run the migration:

```bash
bin/console make:migration
bin/console doctrine:migrations:migrate
```

## Template files

The template files (`.docx`, `.template.json`, images, fonts) are stored in `lle_pdf_generator.path`
(default `data/pdfmodel`). They are uploaded with [VichUploaderBundle](https://github.com/dustin10/VichUploaderBundle):
the `pdf_model` mapping must point to this folder.

```yaml
# config/packages/vich_uploader.yaml
vich_uploader:
    mappings:
        pdf_model:
            upload_destination: '%kernel.project_dir%/data/pdfmodel'
            namer: Vich\UploaderBundle\Naming\UniqidNamer
```

## Rendering tools

Install the tools of the template types you use.

### `word_to_pdf`: unoserver

The `.docx` documents are converted to PDF by unoserver, over HTTP (`lle_pdf_generator.unoserver`):

```yaml
# docker-compose.yaml
services:
    unoserver:
        image: registry.2le.net/2le/2le:unoserver
```

### `crudit_report`: crudit and Typst

Optional: the `crudit_report` type is disabled until `crudit.enabled` is `true`. The bundle manages the templates,
the project installs the tools: the `crudit` command of [Crudit Report](https://github.com/2lenet/crudit-report) and Typst **0.15.0** (the version
of the designer preview; `crudit` refuses another one). Build `crudit` as a static binary for an Alpine container:

```bash
# in crudit-report
CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o crudit ./engine/cmd/crudit
cp bin/typst-0.15.0 typst
```

```yaml
lle_pdf_generator:
    crudit:
        enabled: true
        bin: '%kernel.project_dir%/bin/crudit-report/crudit'
        typst: '%kernel.project_dir%/bin/crudit-report/typst'
```

The designer is a static web application, built for the path where it is served: see
[The Crudit Report designer](crudit-designer.md#installation).

## Admin screen

With [Crudit Bundle](https://github.com/2lenet/CruditBundle), the admin screen is added automatically. Add its menu
entry and give the roles to your users: see [Admin screen](admin-screen.md).
