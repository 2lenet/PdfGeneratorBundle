<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator\Fixtures;

/** Invalid data class: untyped property. */
class Untyped
{
    // @phpstan-ignore missingType.property
    public $value;
}
