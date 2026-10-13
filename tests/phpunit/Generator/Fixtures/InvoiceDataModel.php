<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator\Fixtures;

use Lle\PdfGeneratorBundle\DataModel\CruditDataModelInterface;

class InvoiceDataModel implements CruditDataModelInterface
{
    public static function getName(): string
    {
        return 'invoice';
    }

    public function getLabel(): string
    {
        return 'Invoice';
    }

    public function getDataClass(): string
    {
        return InvoiceData::class;
    }

    public function getSample(): ?object
    {
        return new InvoiceData(new Invoice('EX-1'), [], '1', new Address('Paris'));
    }
}
