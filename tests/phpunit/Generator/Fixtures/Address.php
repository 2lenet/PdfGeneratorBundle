<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator\Fixtures;

/** Nested data class. */
class Address
{
    public function __construct(public string $city, public ?string $zip = null)
    {
    }
}
