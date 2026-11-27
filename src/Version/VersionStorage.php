<?php

namespace Lle\PdfGeneratorBundle\Version;

use Lle\PdfGeneratorBundle\Generator\PdfGenerator;

/**
 * Copies of the uploaded template files, in the hidden folder .versions of the templates folder: the export of the
 * templates skips the hidden elements and the import does not touch them, so the history survives a transfer.
 */
class VersionStorage
{
    public const DIR = '.versions';

    public function __construct(
        protected PdfGenerator $pdfGenerator,
    ) {
    }

    /** Copies a file in the versions folder, under a new unique name keeping its extension; returns this name. */
    public function store(string $file): string
    {
        $dir = $this->dir();
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0o775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create the versions folder ' . $dir);
            }
            // permissions of the templates folder: created by a command (root), it stays writable by the web server
            @chmod($dir, fileperms($this->templatesDir()) & 0o777);
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $name = bin2hex(random_bytes(12)) . ($extension !== '' ? '.' . strtolower($extension) : '');
        if (!@copy($file, $dir . '/' . $name)) {
            throw new \RuntimeException('Cannot copy ' . $file . ' to the versions folder');
        }

        return $name;
    }

    /** Path of a stored copy; only a plain file name of the versions folder is accepted. */
    public function path(string $storedName): string
    {
        if ($storedName === '' || basename($storedName) !== $storedName || str_starts_with($storedName, '.') || str_contains($storedName, '\\')) {
            throw new \InvalidArgumentException('Invalid version file name: ' . $storedName);
        }

        return $this->dir() . '/' . $storedName;
    }

    /** Templates folder (lle_pdf_generator.path), without trailing slash. */
    public function templatesDir(): string
    {
        return rtrim($this->pdfGenerator->getPath(), '/');
    }

    private function dir(): string
    {
        return $this->templatesDir() . '/' . self::DIR;
    }
}
