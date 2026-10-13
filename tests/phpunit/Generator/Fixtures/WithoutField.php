<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator\Fixtures;

/** Invalid data class: object without any field to describe. */
class WithoutField
{
    public function __construct(
        public Hidden $hidden,
    ) {
    }
}
