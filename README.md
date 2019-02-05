# PdfGeneratorBundle

Require: unoconv

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
    return $generator->generateResponse('INVITATION', $data);
    //or
    return $generator->generateByRessourceResponse(TcpdfGenerator::getName(), MyTcpdfClass::class, $data);
}
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
        $this->changeFont('titre');
        $this->w(10,10,'Hello <b>World</b>');
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

You can create an pdf model in bdd with ressource "App\Service\Pdf\MyTcpdfClass" and code MYTCPDF
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



