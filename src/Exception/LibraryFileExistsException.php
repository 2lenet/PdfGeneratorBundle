<?php

namespace Lle\PdfGeneratorBundle\Exception;

/** Another file already has this name in the library of the Crudit Report templates. */
class LibraryFileExistsException extends \RuntimeException
{
    public function __construct(public readonly string $uri)
    {
        parent::__construct($uri . ' already exists in the library');
    }
}
