<?php

namespace Lle\PdfGeneratorBundle\Tests\Generator\Fixtures;

/** Neither Serializer group nor public property. */
class Hidden
{
    // @phpstan-ignore property.onlyWritten
    private string $secret = '';
}
