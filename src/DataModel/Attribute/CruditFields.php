<?php

namespace Lle\PdfGeneratorBundle\DataModel\Attribute;

/**
 * Data class property holding an entity or a list of entities: exported fields, instead of those of the
 * Serializer group (for an entity without group, or to keep only some of them).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CruditFields
{
    /**
     * @param list<string> $fields
     */
    public function __construct(public array $fields)
    {
    }
}
