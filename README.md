# PdfGeneratorBundle

## Installation

`composer require 2lenet/pdf-generator-bundle`


Require: unoconv (for word_to_pdf)
```dockerfile
RUN apt-get update;apt-get install -y unoconv
```
unoconv may have some problems with the www-data user: https://github.com/unoconv/unoconv/issues/241
```dockerfile
RUN mkdir -p /var/www/.cache && chown www-data /var/www/.cache && chgrp www-data /var/www/.cache
RUN mkdir -p /var/www/.config && chown www-data /var/www/.config && chgrp www-data /var/www/.config
```
I think that this can be perfectible.

## Configuration
config (with default value):
```yaml
lle_pdf_generator:
  path: "data/pdfmodel"
  default_generator: "word_to_pdf"
  class: 'Lle\PdfGeneratorBundle\Entity\PdfModel'
```

add routing (for show the ressource use <a href="{{ path('lle_pdf_generator_show_ressource', {'id': item.id}) }}">)
```yaml
lle_pdf_generator:
    resource: "@LlePdfGeneratorBundle/Resources/routing/routes.yaml"
    prefix: /
```
if you create an model without type and with ressource is mydoc.doc the generator will create an pdf based on data/pdfmodel/mydoc.doc with word_to_pdf generator.

## Configure your generic tags

You can easily list the tags used in your models.

To do this, simply declare the route to the page where the tags will be listed. The name of the route is "lle_pdf_generator_admin_balise".

Example in Crudit:

```php

public function getListActions(): array
    {
        $actions = parent::getListActions();

        array_unshift($actions, ListAction::new(
            "action.balise",
            Path::new('lle_pdf_generator_admin_balise'),
            Icon::new("bookmark")
        ));

        return $actions;
    }

```

To complete the list of tags, use Symfony annotation group "**pdfgenerator**" in your entities. For example:

```php
<?php

namespace App\Entity

use Symfony\Component\Serializer\Annotation\Groups;

class Commande
{
    /**
     * @Groups({"pdfgenerator"})
     */
    private $type;
}
```

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

## Use with your entity:

change config "class" of pdf generator "App/Entity/MyModelPdf"
```php

<?php

namespace App\Entity;

use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Doctrine\ORM\Mapping as ORM;
use Lle\PdfGeneratorBundle\Entity as PDF;

/**
 *
 * @ORM\Table(name="lle_pdf_model", indexes={@ORM\Index(name="code_idx", columns={"code"})})
 * @ORM\Entity
 * @Vich\Uploadable
 */
class MyModelPdf implements PDF\PdfModelInterface
{
    use PDF\PdfModelTrait;
}
```
## Use with bdd

The model is a PdfModel with

a code, a ressource, a libelle, a type and a description

you can create a model with 
```
php bin/console lle:pdf-generator:create-model
```

!! Warning if you use your own class and this class has other field with constraint the command not work. !!

## Create your own type

you can create several type of pdf (already exist tcpdf and word_to_pdf)

- word_to_pdf (the ressource is an path of .docx format Microsoft Word XML)
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

## use the word_to_pdf (format Microsoft Word XML)

Create a .docx file /data/pdfmodel/test.docx  with Hello ${name}  

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
    return $generator->generateByRessourceResponse(WordToPdfGenerator::getName(), 'test.docx', $data);
}


```
You can create an pdf model in bdd with ressource "test.docx" and code MYDOC type "word_to_pdf"
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

you can use variables ${@img[logo]:100x200} or ${@img[logo]} for create images. (regex is #^@img\[(\w+)\](:(\d+)x(\d+))?$#)
```php
$generator->generateResponse('MYDOC', [['logo'=> 'logo.png']]);
```
search to {{lle_pdf_generator.path}}/logo.png so default is data/pdfmodel/logo.png
```php
$generator->generateResponse('MYDOC', [['logo'=> '/logo.png']]);
```
search to /logo.png

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

You can add an draw with signature
```php
<?php
/*...*/
$picture = 'signature.png';
$signature = new Signature($generator->getPath().'cert/tcpdf.crt', $password, $info, $pictur);
//or
$pos = [
    'w' => 40, //width default 40
    'h' => 20, //heght default 20
    'x' => 10, //x default pageWidth - w
    'y' => 10, //y default pageHeight - (h*2+5)
    'p' => 1 //page default last page
];
$signature = new Signature($generator->getPath().'cert/tcpdf.crt', $password, $info, $pictur, $pos);
```
You can also add segment or points for create the signature picture
```php
<?php
$signature = new Signature($certif, $password, $info);

$signature->setSegments([[$x1,$y1],[$x2,$y2]], $pos);
//or
$signature->setPoints([$x1,$y1,$x2,$y2], $pos);
//or
$signature->setImage('signe.png', $pos);

$signature->setPosition($pos); // you can use it also
```
### Pdf response
```php
<?php
return $generator->generateByRessourceResponse(WordToPdfGenerator::getName(), 'test.docx', $data, [$signature]);
//or
return $generator->generateResponse('MYMODELCODE', $data, [$signature]);
```

### Pdf Merger

The pdfMerge is the class of instance return by generator
```php
<?php
$pdfMerger = $generator->generateByRessource(WordToPdfGenerator::getName(), 'test.docx', $data);
//or
$pdfMerger = $generator->generate('MYMODELCODE', $data);
$pdf = $generator->signes($pdfMerger, [$signature]); //return an TcpdfFpdi (signe($pdfMerger, $signature) exist also)
$pdf->Output('My pdf', 'D'); // return a signed pdf
$pdfMerger->merge('My pdf', 'D'); // return a unsigned pdf
```
You can't signed a pdfMerger you have to pass by TcpdfFpdi. An PdfMerger instance can never be signed

You can continue to sign an TcpdfFpdi with $generator->signeTcpdfFpdi($pdf, $signature)

You can also use directly the signature instance for sign a Pdfmerger or TcpdfFpdi
```php
<?php
$pdfMerger = $generator->generate('MYMODELCODE', $data);
$signature->signe($pdfMerger)->Output('My pdf', 'D');
```

or

```php
<?php
$pdfMerger = $generator->generate('MYMODELCODE', $data);
$pdf = $pdfMerger->toTcpdfFpdi();
$pdf = $signature->signeTcpdfFpdi($pdf);
$pdf = $signature2->signeTcpdfFpdi($pdf);
$pdf->Output('My pdf', 'D');
```

You can't use several sign with PdfMerger



## ieterable data
create a .docx file and create 2 table (1 line , 3 cells)

- first table cells 1 write ${eleves.nom} , cells 2 write ${eleves.etablissement.nom}, cells 3 write ${@img[eleves.logo]}
- second table cells 1 write ${users.[nom]}, cells 2 write ${users.[adresse][rue]}, cells 3 write ${@img[users.[logo]]}

save it with myiterable.docx
use the Lle\PdfGeneratorBundle\Lib\PdfIterable class

```php
<?php
$data = [
    'eleves' => new PdfIterable($this->em->getRepository(Eleve::class)->findAll()),
    'users' => new PdfIterable([['nom'=>'saenger','adresse'=>['rue'=>'rue du chat'], 'logo'=>'logo.png'], ['nom'=>'boehler', 'adresse'=>['rue'=>'rue du chien'], 'logo.png']]),            
];
return $generator->generateByRessourceResponse(WordToPdfGenerator::getName(), 'myiterable.docx', $data);
```

show it

Warning only the first level of data can to be an PdfIterable, you can't use ${etablissement.eleves}:
```php
<?php
$data = [
    'etablissement' => $etablissement,
    'eleves' => PdfIterable($etablissement->getEleves())
];
```

# Understand the property (word to pdf)

The property is read with propertyAccesor (Symfony) but the first is beetween two "[]": [first].rest
```
the vars ${eleve.etablissement.nom} -> $propertyAccess->getValue($params, '[eleve].etablissement.nom')
the vars ${eleve.etablissement[nom]} -> $propertyAccess->getValue($params, '[eleve].etablissement[nom]')
```
!!! warning use the same systeme if you create your own type !!!



## Merge several model

```php
<?php
return $generator->generateByRessourceResponse(
    TcpdfGenerator::getName(), 
    [MyTcpdfClass::class,AnotherTcpdfClass::class], 
    $data);
```

```php
<?php
return $generator->generateByRessourceResponse(
    [TcpdfGenerator::getName(),WortdToPdfGenerator::getName()], 
    [MyTcpdfClass::class,'mydoc.docx'], 
    $data);
```

in bdd:

```sql
INSERT INTO `lle_pdf_model` (`code`, `path`, `type`) VALUES
('RELANCE_1ANS', 'mydoc.docx,App\\Service\\Pdf\\LotInvitation', 'word_to_pdf,tcpdf')
```

The default type is always the first (here "word_to_pdf")

if none type is defined the type is lle_pdf_generator.default_generator config
