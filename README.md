# PdfGeneratorBundle

Require: unoconv
```dockerfile
RUN apt-get update;apt-get install -y unoconv
```

## Configuration
config (with default value):
```yaml
lle_pdf_generator:
  path: "data/pdfmodel"
  default_generator: "word_to_pdf"
```
if you create an model without type and with ressource is mydoc.doc the generator will create an pdf based on data/pdfmodel/mydoc.doc with word_to_pdf generator.

## Use it
You can use the PDFgenerator with bdd or directly in code
```php
<?php
/**
 * @Route("/pdf")
 */
public function pdf(PdfGenerator $generator, UserRepository $userRepository)
{
    $data = [];
    foreach($userRepository->findAll() as $user){
        $data[] = ['name' => $user->getName()];
    }
    //create an response by Bdd
    return $generator->generateResponse('MYMODELCODE', $data);
    //or
    //create an PdfMerger by Bdd
    $generator->generate('MYMODELCODE', $data)->merge('pdf.pdf','F');
    //or
    //create an PdfMerger by ressource
    return $generator->generateByRessourceResponse(TcpdfGenerator::getName(), MyTcpdfClass::class, $data);
    //or
    //create an response by ressource
    $generator->generateByRessource(TcpdfGenerator::getName(), MyTcpdfClass::class, $data)->merge('pdf.pdf','F');
}
```

You can create an instance of TcpdfFpdi (Tcpdf and Fpdi) with the PdfMerger
```php
<?php
$pdfMerger = $generator->generate('MYMODELCODE', $data)->merge('pdf.pdf','F');
$pdf = $pdfMerger->toTcpdfFpdi();
$pdf->addPage('P');
$pdf->writeHTML('Hello', true, 0, true, 0);
$pdf->Output('file.pdf', 'F');
return new ResponseBinaryFile('file.pdf');
```

## Use with bdd

The model is a PdfModel with

a code, a ressource, a libelle, a type and a description

you can create a model with 
```
php bin/console lle:pdf-generator:create-model
```

## Create your own type

you can create several type of pdf (already exist tcpdf and word_to_pdf)

- word_to_pdf (the ressource is an path of .doc)
- tcpdf (the ressource is an class which extend Lle\PdfGeneratorBundle\Lib\Pdf (Tcpdf and Fpdi))

You can create your own type with an class which extend Lle\PdfGeneratorBundle\Generator\AbstractPdfGenerator



AbstractPdfGenerator implements Lle\PdfGeneratorBundle\Generator\PdfGeneratorInterface (autotagged lle.pdf.generator)

```php
    public static function getName():string; //name of type
    public function generate(string $source, iterable $params, string $savePath):void; //generate the pdf with the ressource $source and parameters $params in a tmp file $savePath
    public function getRessource(string $pdfPath, string $modelRessource): string; //calcule the ressource with pdfPath or not (the ressource can be an class name for exemple)
```

an exemple is:

```php
<?php
class TcpdfGenerator extends AbstractPdfGenerator
{

    private $pdfPath;
    
    public function generate(string $source, iterable $params, string $savePath):void{
        $reflex = new \ReflectionClass($source);
        $pdf = $reflex->newInstance();
        if ($pdf instanceof Pdf) {
            $pdf->setRootPath($this->pdfPath);
            $pdf->setData($params['vars']);
            $pdf->initiate();
            $pdf->generate();
            $pdf->setTitle($pdf->title());
        } else {
            throw new \Exception('PDF GENERATOR ERROR: ressource '.$source.' n\'est pas une class PDF');
        }
        $pdf->output($savePath, 'F');
    }

    //$pdfPath is in config lle_pdf_generator.path (default:data/pdfmodel)
    public function getRessource(string $pdfPath, string $modelRessource): string{
        $this->pdfPath = $pdfPath;
        return $modelRessource;
    }

    public static function getName(): string{
        return 'tcpdf';
    }
}
```

## Use the type tcpdf

An exemple for tcpdf:
```php
<?php

namespace App\Service\Pdf;

use Lle\PdfGeneratorBundle\Lib\Pdf;

class MyTcpdfClass extends Pdf
{

    //$this->rootPath is in config lle_pdf_generator.path (default:data/pdfmodel)
    public function init()
    {
        $this->setSourceFile($this->rootPath . 'background.pdf');
    }
    
    public function myColors()
    {
        return ['blanc' => 'FFFFFF','default'=> '000000', 'red' => 'FF0000'];
    }

    //the fonts is in $this->rootPath.'/fonts'
    public function myFonts()
    {
        return ['titre' => ['size'=>12,'color'=>'noir','family'=>'courier', 'style'=>'BU']];
    }

    public function generate()
    {
        $this->AddPage('P');
        $this->showGrid(5); //is an debug function which show an gride by 5px
        $this->changeFont('titre');
        $this->w(10,10,'Hello <b>'. $this->data['name'] .'</b>');
    }

    public function footer()
    {
    }
}
```

```php
<?php
/**
 * @Route("/pdf")
 */
public function pdf(PdfGenerator $generator, UserRepository $userRepository)
{
    $data = [];
    foreach($userRepository->findAll() as $user){
        $data[] = ['name' => $user->getName()];
    }
    return $generator->generateByRessourceResponse(TcpdfGenerator::getName(), MyTcpdfClass::class, $data);
}
```

You can create an pdf model in bdd with ressource "App\Service\Pdf\MyTcpdfClass" and code MYTCPDF type "tcpdf"
```php
<?php
/**
 * @Route("/pdf")
 */
public function pdf(PdfGenerator $generator, UserRepository $userRepository)
{
    $data = [];
    foreach($userRepository->findAll() as $user){
        $data[] = ['name' => $user->getName()];
    }
    return $generator->generateResponse('MYTCPDF', $data);
}
```

## use the word_to_pdf

Create a .doc file /data/pdfmodel/test.doc  with Hello ${name}  

```php
<?php
/**
 * @Route("/pdf")
 */
public function pdf(PdfGenerator $generator, UserRepository $userRepository)
{
    $data = [];
    foreach($userRepository->findAll() as $user){
        $data[] = ['name' => $user->getName()];
    }
    return $generator->generateByRessourceResponse(WordToPdfGenerator::getName(), 'test.doc', $data);
}
```

You can create an pdf model in bdd with ressource "test.doc" and code MYDOC type "word_to_pdf"
```php
<?php
/**
 * @Route("/pdf")
 */
public function pdf(PdfGenerator $generator, UserRepository $userRepository)
{
    $data = [];
    foreach($userRepository->findAll() as $user){
        $data[] = ['name' => $user->getName()];
    }
    return $generator->generateResponse('MYDOC', $data);
}
```
https://phpword.readthedocs.io/en/latest/templates-processing.html

##Sign pdf

Use the Lle\PdfGeneratorBundle\Lib\Signature class you can sign an pdf response or an PdfMerge

### Cretae the signature

```
openssl req -x509 -nodes -days 365000 -newkey rsa:1024 -keyout tcpdf.crt -out tcpdf.crt
openssl pkcs12 -export -in tcpdf.crt -out tcpdf.p12
```

```php
<?php
$password = '***';
$info = [
    'Name' => 'name',
    'Location' => 'location',
    'Reason' => 'reason',
    'ContactInfo' => 'url',
];
$signature = new Signature($generator->getPath().'cert/tcpdf.crt', $password, $info);
```
### Pdf response
```php
<?php
return $generator->generateByRessourceResponse(WordToPdfGenerator::getName(), 'test.doc', $data, $signature);
//or
return $generator->generateResponse('MYMODELCODE', $data, $signature);
```

### Pdf Merger

The pdfMerge is the class of instance return by generator
```php
<?php
$pdfMerger = $generator->generateByRessource(WordToPdfGenerator::getName(), 'test.doc', $data);
//or
$pdfMerger = $generator->generate('MYMODELCODE', $data);
$pdf = $generator->sign($pdfMerger, $signature); //return an TcpdfFpdi
$pdf->Output('My pdf', 'D'); // return a signed pdf
$pdfMerger->merge('My pdf', 'D'); // return a unsigned pdf
```
You can't signed a pdfMerger you have to pass by TcpdfFpdi. An PdfMerger instance can never be signed

You can also use directly the signature instance for sign a Pdfmerger
```php
<?php
$pdfMerger = $generator->generate('MYMODELCODE', $data);
$signature->signe($pdfMerger)->Output('My pdf', 'D');
```

## Future features
The iterable data for word_to_pdf not work for the moment.

you can JUST test it directly in Lle\PdfGeneratorBundle\Generator\PdfGenerator l 46 

static::ITERABLE => []

replace by

```php
static::ITERABLE => [
   'table1' => [['name' => 'A', 'pseudo'=>'a'],['name' => 'B', 'pseudo'=>'b']]
],
```

In word file create an table with one row and two cells:
cells1 containt ${name}
cells2 containt ${pseudo}

Use it only for see or suggest an pull request





