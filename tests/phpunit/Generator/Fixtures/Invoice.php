<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

/** "Entity": fields exported by the pdfgenerator group. */
class Invoice
{
    #[Groups(['pdfgenerator'])]
    public string $number;

    #[Groups(['pdfgenerator'])]
    public ?\DateTime $issuedOn;

    public string $internal = 'not exported';

    public function __construct(string $number, ?\DateTime $issuedOn = null)
    {
        $this->number = $number;
        $this->issuedOn = $issuedOn;
    }
}
