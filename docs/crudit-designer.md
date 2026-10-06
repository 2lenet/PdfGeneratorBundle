# The Crudit Report designer

The designer of [Crudit Report](https://github.com/2lenet/crudit-report) is a static web application (Vue 3 + the
rendering engine compiled to WebAssembly). The bundle serves it the templates: the admin action "Edit in the
designer" opens the designer on a template, which is loaded and saved through the bundle routes. The exact preview is
compiled in the browser, identical to the PDF.

## Installation

The designer must be built for the path where it is served (it loads its engine and fonts from there). In
crudit-report (Node ≥ 22.12):

```bash
make designer-build BASE=/crudit-designer/
cp -r designer/dist /path/to/project/public/crudit-designer
```

```yaml
lle_pdf_generator:
    crudit:
        designer_url: '/crudit-designer/index.html'   # default
```

Apache does not serve `/crudit-designer/` alone: the URL is `/crudit-designer/index.html`.

**Cache**: a browser must not keep an old designer after an update. The engine URL contains a hash of its content,
and the redirection of the bundle adds the date of `index.html` (`&v=…`) when the designer is served by the project
(`public/`). In production, also send `Cache-Control: no-cache` for `crudit-designer/index.html` and
`crudit-designer/engine/`.

## Routes

Loaded only when `crudit.enabled` is `true`. All require `ROLE_PDFMODEL_DESIGNER` (`PdfModelRoles::DESIGNER`). A template of another type than `crudit_report`
gives a 404.

| Route | |
|---|---|
| `lle_pdf_generator_crudit_edit` (GET `/pdfmodel/designer/{id}`) | Redirects to the designer on this template (`?back=`: return URL, default: referer) |
| `lle_pdf_generator_crudit_template` (GET `/pdfmodel/template/{id}`) | Template JSON; with a [data source](data-sources.md), completed with its parameters and its sample |
| `lle_pdf_generator_crudit_template_save` (PUT `/pdfmodel/template/{id}`) | Saves the JSON: 400 if unreadable, 422 with the diagnostics of `crudit validate` if invalid, atomic write otherwise. With a data source, the provided parameters are put back from the source |
| `lle_pdf_generator_crudit_library` (GET `/pdfmodel/library/{path}`) | Image or font of the library (`lle_pdf_generator.path`); `../` and other extensions give a 404; an SVG opened directly runs no script (`Content-Security-Policy: sandbox`) |
| `lle_pdf_generator_crudit_library_list` (GET `/pdfmodel/library`) | `{ "files": [{ "uri", "kind", "size", "modified", "widthPx"?, "heightPx"? }] }` |
| `lle_pdf_generator_crudit_library_upload` (POST `/pdfmodel/library`) | Uploads an image (png, jpg, gif, svg, webp) to `assets/` or a font (ttf, otf) to `fonts/`, 10 MB max: multipart field `file`, header `X-Requested-With: XMLHttpRequest`. 201 `{ "uri" }`; 409 `{ "error", "uri" }` if another file has this name (field `overwrite=1` to replace it); 400 / 422 `{ "error" }` |

The redirection gives the designer the URLs of these routes (`template`, `library`, `files`), the return URL and the
template label: see the hosted mode in the crudit-report documentation (`docs/12-designer-heberge.md`).

## In the designer

- "Save" sends the template to the application; "Import" replaces it with a JSON file (to save), "Export" downloads
  it. Autosave in the browser, per template; leaving with unsaved changes asks for confirmation.
- Resources tab: "Send to the library…" for images and fonts (shared by all the templates), and the list of the
  library files.
- Data tab: with a data source, its parameters are read-only (only computed parameters can be added); without
  source, the structure can be imported from / exported to a `.schema.json` file (a copy).

## Owner of the files

A template saved from the application belongs to the user of the web server (root in a container), like the files
uploaded by Vich. The permissions of an existing file are kept, not its owner.
