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
| `pdfmodel/` | The files of the templates, taken from `lle_pdf_generator.path`: the files named by the `path` column of the rows (`.docx`, `.template.json`…) and the library folders `assets/` and `fonts/` (hidden files excluded) |

The other files of `lle_pdf_generator.path` (generated PDFs, uploads of other entities…) are **not exported, and not
touched by the import**.

**The import replaces all the templates**:

- the table is emptied then filled with the rows of the archive (ids kept). A column of the archive that the table
  does not have (archive of another version) is ignored, and the final message names it;
- the files of the old templates (named by their `path`) and the `assets/` and `fonts/` folders are replaced by those
  of the archive. A file of the target that the archive would overwrite is replaced too.

Before writing anything, the import checks the archive and refuses:

- a missing or unknown `pdfmodel.json`;
- a path with `..`, absolute, or with a hidden element;
- a file that is neither named by a row of the archive nor in `assets/` or `fonts/`;
- more than 10,000 files, or more than 512 MB once unzipped (bytes really extracted, not the sizes announced by the
  archive), or a `pdfmodel.json` bigger than 64 MB.

The files are extracted to a hidden temporary folder inside the templates folder, then swapped with the old ones
during the database transaction; on failure, the transaction is rolled back and the old files are put back. If some
old files cannot be put back, they are kept in a hidden folder `.backup-…` of the templates folder, named by the
error (`RuntimeException`, logged by Symfony): copy them back by hand.

## Foreign keys

The import runs `DELETE FROM` on the templates table, then inserts the rows of the archive. If an entity of the
project references the templates (foreign key to `lle_pdf_model`), the delete fails (the import is cancelled and
nothing changes) or cascades to the referencing rows, depending on the `ON DELETE` of the key. Do not use the import
in that case, or check the foreign keys first.
