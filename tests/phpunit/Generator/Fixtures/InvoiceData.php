<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator\Fixtures;

use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditFields;
use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditImage;
use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditLabel;
use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditType;

/** Data class of the "invoice" data source. */
class InvoiceData
{
    public static string $ignored = 'static';

    public function __construct(
        public Invoice $invoice,
        /** @var list<Line> */
        #[CruditFields(['label'])]
        public iterable $lines,
        /** Doctrine decimal: a string */
        #[CruditType('number')]
        public string $total,
        public Address $address,
        #[CruditImage]
        public ?string $logo = null,
        #[CruditLabel('Current date')]
        public string $date = '',
        public int $pageCount = 1,
    ) {
    }
}
