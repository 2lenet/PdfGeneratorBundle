<?php

namespace Lle\PdfGeneratorBundle\DataModel\Attribute;

/** Data class property: label shown in the designer (otherwise the field.<name> translation). */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CruditLabel
{
    public function __construct(public string $label)
    {
    }
}
