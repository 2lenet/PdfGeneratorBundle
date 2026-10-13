# `word_to_pdf`: Word templates

The resource is a `.docx` file of `lle_pdf_generator.path`. Its `${variables}` are replaced with
[PhpWord](https://phpword.readthedocs.io/en/latest/templates-processing.html), then the document is converted to PDF
by unoserver (see [Installation](installation.md#word_to_pdf-unoserver)).

Create `data/pdfmodel/hello.docx` containing `Hello ${name}`, and a template with this resource, code `HELLO` and
type `word_to_pdf` (the default type):

```php
return $pdfGenerator->generateResponse('HELLO', [['name' => $user->getName()]]);
// or without template in database
return $pdfGenerator->generateByRessourceResponse(WordToPdfGenerator::getName(), 'hello.docx', [['name' => 'Ada']]);
```

## Variables

A variable is read in the data set with the Symfony PropertyAccessor; its first part is the key of the data set:

```
${eleve.etablissement.nom}   → $propertyAccessor->getValue($data, '[eleve].etablissement.nom')
${eleve.etablissement[nom]}  → $propertyAccessor->getValue($data, '[eleve].etablissement[nom]')
```

`DateTime` values are formatted `d/m/Y`. An unknown variable keeps its name, unless the option
`PdfGenerator::OPTION_EMPTY_NOTFOUND_VALUE` is set (see [Options](usage.md#options)).

## Images

`${@img[logo]}` or `${@img[logo]:100x200}` (width × height) inserts the image whose path is the `logo` value:

```php
$pdfGenerator->generateResponse('HELLO', [['logo' => 'logo.png']]);   // data/pdfmodel/logo.png
$pdfGenerator->generateResponse('HELLO', [['logo' => '/var/www/logo.png']]); // absolute path
```

## Lists

A table row is repeated for each item of a `Lle\PdfGeneratorBundle\Lib\PdfIterable`. Create a table with one row,
for example with the cells `${eleves.nom}`, `${eleves.etablissement.nom}`, `${@img[eleves.logo]}`, or for arrays
`${users.[nom]}`, `${users.[adresse][rue]}`, `${@img[users.[logo]]}`:

```php
$data = [
    'eleves' => new PdfIterable($eleveRepository->findAll()),
    'users' => new PdfIterable([
        ['nom' => 'Saenger', 'adresse' => ['rue' => 'rue du chat'], 'logo' => 'logo.png'],
        ['nom' => 'Boehler', 'adresse' => ['rue' => 'rue du chien'], 'logo' => 'logo.png'],
    ]),
];
return $pdfGenerator->generateByRessourceResponse(WordToPdfGenerator::getName(), 'list.docx', [$data]);
```

Only the first level of the data set can be a `PdfIterable`: `${etablissement.eleves}` does not work; give
`'eleves' => new PdfIterable($etablissement->getEleves())`.
