# History of the template files

Each file uploaded for a template is kept, with who uploaded it and when. A previous file can be downloaded, or
restored as the current file of the template.

## What is recorded

A version is recorded at each **upload** of the `file` field of a template (VichUploader `vich_uploader.post_upload`
event), whatever the screen or the code that uploads it, and at each restoration. The changes of the other fields,
the files saved by the [designer](crudit-designer.md) and the [import of an archive](transfer.md) are not recorded.

| Field of `PdfModelVersion` | |
|---|---|
| `modelId`, `modelCode` | Template (id, and its code at the upload) |
| `number` | Version number, from 1 for each template |
| `originalName`, `size` | Name of the file on the computer of the author, size in bytes |
| `createdAt`, `createdBy` | Date, and identifier of the logged-in user (`getUserIdentifier()`); empty in a command |
| `restoredFrom` | Number of the restored version, for a restoration |
| `storedName` | Name of the copy in the versions folder |

The versions are stored in the `lle_pdf_model_version` table, without a foreign key to the templates table: the
[import of an archive](transfer.md) empties and refills that table (ids kept), which a foreign key would block. The
history of a deleted template is kept.

The files are copied in the hidden folder `.versions/` of the templates folder (`lle_pdf_generator.path`). The export
of the templates skips hidden elements and the import does not touch them: the history stays on its platform. Back up
this folder with the templates folder.

A version is saved by a second flush, after the one of the template: if this second flush fails, the file is uploaded
without its version.

## Show page

The [admin screen](admin-screen.md) of the bundle has a "File history" tab: versions, the latest first, with "Download"
and "Restore" actions.

With your own screen, add the tab to its `getTabs()` (translation key `tab.pdfmodel_versions`, in the domain of your
screen):

```php
use Lle\PdfGeneratorBundle\Crudit\PdfModelVersionTab;

public function getTabs(): array
{
    return [PdfModelVersionTab::LABEL => PdfModelVersionTab::new()];
}
```

Without Crudit, render the history in a template:

```twig
{{ render(controller('Lle\\PdfGeneratorBundle\\Controller\\PdfModelVersionController::history', {resource: model})) }}
```

## Routes and roles

| Route | Role | |
|---|---|---|
| `lle_pdf_generator_version_download` (GET `/pdfmodel/version/{id}`) | `ROLE_PDFMODEL_SHOW` | File of the version, under its original name |
| `lle_pdf_generator_version_restore` (POST `/pdfmodel/version/{id}/restore`) | `ROLE_PDFMODEL_ACTION_RESTORE` | Restores the version (CSRF token `_token`, id `PdfModelVersionController::CSRF_TOKEN_ID`), then redirects to the referer with a message |

`ROLE_PDFMODEL_ACTION_RESTORE` (`PdfModelRoles::RESTORE`) is not the role of a Crudit action: declare it in the
credential warmup of the project (or the role hierarchy without CredentialBundle).

A restoration copies the version as a new file of the templates folder, deletes the previous current file (it has its
own copy in `.versions/`) and records a new version (`restoredFrom`).

## Existing templates

The templates uploaded before the history have no version. Create their first version (date: `updatedAt` of the
template, author unknown):

```bash
bin/console lle:pdf-generator:init-versions
```

Only the templates with a single file of the templates folder and no version are concerned: the command can be run
again.
