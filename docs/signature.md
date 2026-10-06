# Sign documents

`Lle\PdfGeneratorBundle\Lib\Signature` signs a generated document.

## Create the certificate

```bash
openssl req -x509 -nodes -days 365000 -newkey rsa:1024 -keyout tcpdf.crt -out tcpdf.crt
openssl pkcs12 -export -in tcpdf.crt -out tcpdf.p12
```

```php
$info = [
    'Name' => 'name',
    'Location' => 'location',
    'Reason' => 'reason',
    'ContactInfo' => 'url',
];
$signature = new Signature($pdfGenerator->getPath() . 'cert/tcpdf.crt', $password, $info);
```

## Signature picture

```php
$position = [
    'w' => 40, // width, default 40
    'h' => 20, // height, default 20
    'x' => 10, // default: page width - w
    'y' => 10, // default: page height - (h * 2 + 5)
    'p' => 1,  // page, default: last page
];
$signature = new Signature($certificate, $password, $info, 'signature.png', $position);

// or draw it
$signature->setSegments([[$x1, $y1], [$x2, $y2]], $position);
$signature->setPoints([$x1, $y1, $x2, $y2], $position);
$signature->setImage('signature.png', $position);
$signature->setPosition($position);
```

## Signed response

```php
return $pdfGenerator->generateResponse('INVOICE', $dataSets, [$signature]);
return $pdfGenerator->generateByRessourceResponse(WordToPdfGenerator::getName(), 'invoice.docx', $dataSets, [$signature]);
```

## Sign a PdfMerger

A `PdfMerger` cannot be signed itself: convert it to a TCPDF + FPDI instance.

```php
$merger = $pdfGenerator->generate('INVOICE', $dataSets);
$pdf = $pdfGenerator->signes($merger, [$signature]);  // or signe($merger, $signature)
$pdf->Output('invoice.pdf', 'D');                     // signed
$merger->merge('download', 'invoice.pdf');            // not signed

// several signatures
$pdf = $merger->toTcpdfFpdi();
$pdf = $signature->signeTcpdfFpdi($pdf);
$pdf = $signature2->signeTcpdfFpdi($pdf);
$pdf->Output('invoice.pdf', 'D');
```
