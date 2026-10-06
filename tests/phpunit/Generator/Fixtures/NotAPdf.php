<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator\Fixtures;

/** Class of the application that a template path must not instantiate. */
class NotAPdf
{
    public static bool $created = false;

    public function __construct()
    {
        self::$created = true;
    }
}
