# Upgrade guide

## To 5.0 (Crudit Report, admin screen)

### Database

```sql
ALTER TABLE lle_pdf_model DROP check_file, DROP datamodel, ADD datasource VARCHAR(64) DEFAULT NULL;
```

Generate it with `bin/console make:migration`. The `check_file` and `datamodel` columns were not used;
`datasource` holds the [data source](data-sources.md) of a `crudit_report` template.

`PdfModelInterface` has two new methods, `getDatasource()` and `setDatasource()` (in `PdfModelTrait`): add them if
your entity implements the interface without the trait.

### Routes

All the routes are now under `/pdfmodel/`, take the template id in the path and check a role. The route names do not
change; update the URLs written by hand:

| Route | Before | Now | Role |
|---|---|---|---|
| `lle_pdf_generator_download_model` | `/admin/pdfgen/downloadModele?id=` | GET `/pdfmodel/download/{id}` | `ROLE_PDFMODEL_SHOW` |
| `lle_pdf_generator_show_model` | `/admin/pdfgen/showModele?id=` | GET `/pdfmodel/pdf/{id}` | `ROLE_PDFMODEL_SHOW` |

Removed: `lle_pdf_generator_check_model` (`/admin/pdfgen/checkModele`) and the tags page
(`lle_pdf_generator_admin_balise`, templates `views/balise/`).

A template path (`PdfModel::path`) must now stay in the templates folder: a resource with `..` or an absolute path is
refused when generating (`RuntimeException`) and when downloading (404). A tcpdf template must name a subclass of
`Lle\PdfGeneratorBundle\Lib\Pdf`, checked before the class is instantiated.

`/pdfmodel/` must be behind the firewall that authenticates the users of the admin: with a firewall limited to
`^/admin`, the user is not authenticated there and every route answers 403. Check `security.firewalls.*.pattern` and
`access_control`, and `bin/console debug:router | grep pdfmodel` for a route of the project on the same paths.

### Configuration

- `lle_pdf_generator.data_models` is deprecated and ignored: delete it from your configuration.
- New options: `screens.enabled` and `crudit.*` (see [Configuration reference](configuration.md)). The
  `crudit_report` type is disabled by default: set `crudit.enabled: true` once crudit, Typst and the designer are
  installed.

### Admin screen

With Crudit Bundle, the bundle now provides the templates screen (see [Admin screen](admin-screen.md)):

- delete the PdfModel screen of the project (config, controller, datasource, filters, form, templates), or keep it
  with `lle_pdf_generator.screens.enabled: false`;
- point the menu entry to `PdfModelCrudConfig::ROOT_ROUTE . '_index'`;
- the screen is named `PDFMODEL`: the roles already given (`ROLE_PDFMODEL_INDEX`, `_SHOW`…) stay valid. Run
  `bin/console lle:credential:warmup` and give the new roles `ROLE_PDFMODEL_DESIGNER`, `ROLE_PDFMODEL_EXPORT` and
  `ROLE_PDFMODEL_IMPORT`;
- the translations of the screen are in the bundle (domain `PdfGeneratorBundle`): the keys of the project for its
  former screen can be removed.

### New features

- [`crudit_report` type](crudit-report.md) and its [designer](crudit-designer.md);
- [data sources](data-sources.md);
- [copy of the templates from one platform to another](transfer.md).

## To 3.0

- `@LlePdfGeneratorBundle/Resources/routing/routes.yaml` is now `@LlePdfGeneratorBundle/Resources/config/routes.yaml`.
- `Lle\PdfGeneratorBundle\Entity\PdfModelCustomFileTrait` is replaced by `Lle\PdfGeneratorBundle\Entity\PdfModelTrait`,
  which now includes the `$file` property.
- The route `lle_pdf_generator_show_ressource` is now `lle_pdf_generator_show_model`.
- The route `lle_pdf_generator_show_pdf` is now `lle_pdf_generator_download_model`.
