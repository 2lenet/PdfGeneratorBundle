# Copy the templates from one platform to another

The admin screen exports all the templates to a zip archive, and imports such an archive on another platform
(`Lle\PdfGeneratorBundle\Transfer\ModelTransfer`).

| Route | Role | |
|---|---|---|
| `lle_pdf_generator_models_export` (GET `/pdfmodel/archive`) | `ROLE_PDFMODEL_EXPORT` | Downloads `pdf-templates-<host>-<date>.zip` |
| `lle_pdf_generator_models_import` (POST `/pdfmodel/archive`) | `ROLE_PDFMODEL_IMPORT` | Imports the `archive` file (CSRF token `_token`, id `ModelTransferController::CSRF_TOKEN_ID`), then redirects to the referer with a message |

The archive contains:

| Entry | |
|---|---|
| `pdfmodel.json` | The rows of the templates table, **read by the import** |
| `pdfmodel.sql` | The same rows as SQL (`DELETE` then `INSERT`), to read or import by hand. **Never executed by the import** |
| `pdfmodel/` | The `lle_pdf_generator.path` folder: `.docx`, `.template.json`, images and fonts of the library (hidden files excluded) |

**The import replaces everything**: the table is emptied then filled with the rows of the archive (ids kept), and
the content of the folder is replaced (hidden files of the target, such as `.gitkeep`, are kept). A column of the
archive that the table does not have (archive of another version) is ignored, and the final message names it.

Before writing anything, the import checks the archive and refuses:

- a missing or unknown `pdfmodel.json`;
- a path with `..`, absolute, or with a hidden element;
- more than 10,000 files, or more than 512 MB once unzipped.

The files are extracted to a hidden temporary folder inside the templates folder, then swapped with the old ones
during the database transaction; on failure, the transaction is rolled back and the old files are put back.

Requires the `zip` PHP extension.
