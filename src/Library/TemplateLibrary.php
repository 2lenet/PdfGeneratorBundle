<?php

namespace Lle\PdfGeneratorBundle\Library;

use Lle\PdfGeneratorBundle\Exception\LibraryFileExistsException;

/**
 * Library of the crudit_report templates, shared by all the templates of the templates folder: images in assets/
 * (asset refs) and fonts in fonts/, readable by Typst.
 */
class TemplateLibrary
{
    /** Files of the library: extension → kind. */
    public const EXTENSIONS = [
        'png' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'gif' => 'image', 'svg' => 'image', 'webp' => 'image',
        'ttf' => 'font', 'otf' => 'font',
    ];

    public const MAX_SIZE = 10 << 20;

    /**
     * Files of the template library ($dir and its subfolders) usable in a template: images (with their
     * dimensions, except SVG) and fonts, sorted by URI.
     *
     * @return list<array{uri: string, kind: string, size: int, modified: string, widthPx?: int, heightPx?: int}>
     */
    public function list(string $dir): array
    {
        $root = realpath($dir);
        if (!$root) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $kind = self::EXTENSIONS[strtolower($file->getExtension())] ?? null;
            $uri = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
            if ($kind === null || !$file->isFile() || preg_match('#(^|/)\.#', $uri)) {
                continue;
            }

            $entry = [
                'uri' => $uri,
                'kind' => $kind,
                'size' => (int)$file->getSize(),
                'modified' => date(\DateTimeInterface::RFC3339, (int)$file->getMTime()),
            ];
            $info = $kind === 'image' ? @getimagesize($file->getPathname()) : false;
            if (is_array($info) && $info[0] > 0) {
                $entry['widthPx'] = $info[0];
                $entry['heightPx'] = $info[1];
            }
            $files[] = $entry;
        }
        usort($files, fn (array $a, array $b): int => strcmp($a['uri'], $b['uri']));

        return $files;
    }

    /**
     * Adds a file to the template library ($dir): image in assets/, font in fonts/.
     * The name is simplified; an identical file already there is reused. Returns the URI to use in the template
     * (asset ref or font src).
     *
     * @throws \InvalidArgumentException file too big, extension refused or content that does not match
     * @throws LibraryFileExistsException another file already has this name, and $overwrite is false
     */
    public function store(string $dir, string $file, string $originalName, bool $overwrite = false): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $kind = self::EXTENSIONS[$extension] ?? null;

        if ($kind === null) {
            throw new \InvalidArgumentException(
                $originalName . ': extension refused (' . implode(', ', array_keys(self::EXTENSIONS)) . ')'
            );
        }
        if (filesize($file) > self::MAX_SIZE) {
            throw new \InvalidArgumentException(
                $originalName . ': file too big (' . (self::MAX_SIZE >> 20) . ' MB max)'
            );
        }
        if (!$this->matchesExtension($file, $extension)) {
            throw new \InvalidArgumentException(
                $originalName . ': the content does not match the extension ' . $extension
            );
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        // accents removed (é → e); without intl, non-ASCII characters become dashes
        if (class_exists(\Normalizer::class)) {
            $base = (string)preg_replace('/\p{Mn}+/u', '', (string)\Normalizer::normalize($base, \Normalizer::FORM_D));
        }
        $base = trim((string)preg_replace('/[^a-z0-9_-]+/', '-', strtolower($base)), '-') ?: 'file';
        $base = substr($base, 0, 80);

        $sub = $kind === 'font' ? 'fonts' : 'assets';
        $target = rtrim($dir, '/') . '/' . $sub;
        if (!is_dir($target)) {
            mkdir($target, 0o775, true);
        }

        $uri = $sub . '/' . $base . '.' . $extension;
        $path = $target . '/' . $base . '.' . $extension;

        if (file_exists($path)) {
            if (hash_file('sha256', $path) === hash_file('sha256', $file)) {
                return $uri;
            }
            if (!$overwrite) {
                throw new LibraryFileExistsException($uri);
            }
        }

        // atomic write: a running render never reads a half-copied file
        $tmp = $path . '.' . uniqid() . '.tmp';
        copy($file, $tmp);
        chmod($tmp, 0o644);
        rename($tmp, $path);

        return $uri;
    }

    /** The content must match the extension: readable image, or TrueType / OpenType font signature. */
    private function matchesExtension(string $file, string $extension): bool
    {
        if ($extension === 'svg') {
            return (bool)preg_match('/<svg[\s>]/i', (string)file_get_contents($file, false, null, 0, 1024));
        }

        if (self::EXTENSIONS[$extension] === 'font') {
            return in_array(file_get_contents($file, false, null, 0, 4), ["\x00\x01\x00\x00", 'OTTO', 'true'], true);
        }

        $types = [
            'png' => IMAGETYPE_PNG,
            'jpg' => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG,
            'gif' => IMAGETYPE_GIF,
            'webp' => IMAGETYPE_WEBP,
        ];
        $info = @getimagesize($file);

        return is_array($info) && $info[2] === $types[$extension];
    }
}
