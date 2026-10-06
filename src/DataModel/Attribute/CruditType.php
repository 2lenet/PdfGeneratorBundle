<?php

namespace Lle\PdfGeneratorBundle\DataModel\Attribute;

/**
 * Data class property: parameter type, when the PHP type is not enough (e.g. 'date' for a DateTime whose day
 * only matters). Types: string, number, integer, boolean, date, datetime, image.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CruditType
{
    public function __construct(public string $type)
    {
    }
}
