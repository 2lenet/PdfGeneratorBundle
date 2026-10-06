<?php

namespace Lle\PdfGeneratorBundle\Exception;

/**
 * Failed import of the PDF templates whose old files could not be put back: they are kept in the backup folder,
 * to copy back by hand.
 */
class ImportBackupException extends \RuntimeException
{
    /** @param list<string> $lost */
    public function __construct(public readonly array $lost, public readonly string $backup, \Throwable $previous)
    {
        parent::__construct(sprintf(
            'PDF templates import failed (%s) and the old files %s could not be put back: they are kept in %s',
            $previous->getMessage(),
            implode(', ', $lost),
            $backup,
        ), 0, $previous);
    }
}
