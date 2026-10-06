<?php

namespace Lle\PdfGeneratorBundle\Transfer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Generator\CruditReportGenerator;
use Lle\PdfGeneratorBundle\Exception\ImportBackupException;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;

/**
 * Copies the PDF templates from one platform to another (local ↔ production): a zip archive holds the rows of the
 * templates table (pdfmodel.json, read on import, and pdfmodel.sql, to read or import by hand) and the files of the
 * templates (pdfmodel/: files named by the path column of the rows, images and fonts of the library in assets/ and
 * fonts/). The other files of the templates folder are neither exported nor touched by the import.
 *
 * Import replaces all the templates: the table is emptied then filled with the rows of the archive (ids kept), the
 * files of the old templates and the library are replaced by those of the archive. The .sql file is never executed.
 */
class ModelTransfer
{
    public const DATA_FILE = 'pdfmodel.json';

    public const SQL_FILE = 'pdfmodel.sql';

    public const FILES_DIR = 'pdfmodel/';

    public const FORMAT = 1;

    /** Folders of the template library, exported and replaced as a whole. */
    public const LIBRARY_DIRS = ['assets', 'fonts'];

    /** Maximum uncompressed size of the archive (bytes really extracted), and maximum number of files. */
    public const MAX_SIZE = 512 << 20;

    public const MAX_FILES = 10000;

    /** Maximum size of pdfmodel.json, read in memory. */
    public const MAX_DATA_SIZE = 64 << 20;

    public function __construct(
        protected EntityManagerInterface $em,
        protected PdfGenerator $pdfGenerator,
    ) {
    }

    /** The zip PHP extension is suggested by the bundle, not required: without it, no export nor import. */
    public static function isAvailable(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /** Creates the archive in a temporary file and returns its path (to delete once sent). */
    public function export(): string
    {
        $this->checkAvailable();
        $conn = $this->em->getConnection();
        [$table, $id] = $this->table();
        $rows = $conn->fetchAllAssociative(
            'SELECT * FROM ' . $this->quoteName($table) . ' ORDER BY ' . $this->quoteName($id)
        );

        $file = tempnam(sys_get_temp_dir(), 'pdfmodel') ?: throw new \RuntimeException('Cannot create a temporary file');
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create the archive');
        }

        $zip->addFromString(self::DATA_FILE, json_encode(
            ['format' => self::FORMAT, 'table' => $table, 'exportedAt' => date(\DateTimeInterface::RFC3339), 'rows' => $rows],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . "\n");
        $zip->addFromString(self::SQL_FILE, $this->sql($conn, $table, $rows));

        $root = rtrim($this->pdfGenerator->getPath(), '/');
        foreach ($this->files($root, $this->modelFiles($root, $rows)) as $relative) {
            $zip->addFile($root . '/' . $relative, self::FILES_DIR . $relative);
        }

        if (!$zip->close()) {
            throw new \RuntimeException('Cannot write the archive');
        }

        return $file;
    }

    /**
     * Imports an archive made by export(): replaces the rows of the table, the files of the templates and the library.
     * Nothing is changed if the archive is refused or if writing fails.
     *
     * A column of the archive that the table does not have (archive of another version of the bundle) is ignored.
     *
     * @return array{models: int, files: int, ignored: list<string>} ignored: ignored columns
     *
     * @throws \InvalidArgumentException unreadable or invalid archive
     */
    public function import(string $archive): array
    {
        $this->checkAvailable();
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::RDONLY) !== true) {
            throw new \InvalidArgumentException('Unreadable zip archive');
        }

        try {
            [$data, $entries] = $this->readArchive($zip);
            [$columns, $ignored] = $this->checkRows($data);

            $root = rtrim($this->pdfGenerator->getPath(), '/');
            $this->checkEntries($entries, $this->modelPaths($data['rows']));
            $staging = $root . '/.import-' . bin2hex(random_bytes(6));
            mkdir($staging, 0o775, true);
            try {
                $size = 0;
                foreach ($entries as $index => $relative) {
                    $this->extract($zip, $index, $staging . '/' . $relative, $size);
                }

                $this->replace($root, $staging, $data['rows'], $columns);
            } finally {
                $this->remove($staging);
            }
        } finally {
            $zip->close();
        }

        return ['models' => count($data['rows']), 'files' => count($entries), 'ignored' => $ignored];
    }

    /**
     * Data (pdfmodel.json) and files of the templates folder (index in the archive → relative path).
     *
     * @return array{0: array{rows: list<array<string, mixed>>}, 1: array<int, string>}
     */
    private function readArchive(\ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_FILES) {
            throw new \InvalidArgumentException('Archive refused: more than ' . self::MAX_FILES . ' files');
        }

        $data = null;
        $entries = [];
        $size = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string)($stat['name'] ?? '');
            $size += (int)($stat['size'] ?? 0);
            if ($size > self::MAX_SIZE) {
                throw new \InvalidArgumentException('Archive refused: more than ' . (self::MAX_SIZE >> 20) . ' MB once uncompressed');
            }

            if ($name === self::DATA_FILE) {
                $data = json_decode($this->readData($zip, $i), true);
            } elseif (str_starts_with($name, self::FILES_DIR) && !str_ends_with($name, '/')) {
                $relative = substr($name, strlen(self::FILES_DIR));
                if (!$this->safePath($relative)) {
                    throw new \InvalidArgumentException('Archive refused: forbidden path ' . $name);
                }
                $entries[$i] = $relative;
            }
        }

        if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT || !is_array($data['rows'] ?? null)) {
            throw new \InvalidArgumentException('Archive refused: ' . self::DATA_FILE . ' missing or of an unknown format');
        }

        return [$data, $entries];
    }

    /** Content of pdfmodel.json, read from a stream to refuse a file bigger than announced. */
    private function readData(\ZipArchive $zip, int $index): string
    {
        $stream = $zip->getStreamIndex($index) ?: throw new \InvalidArgumentException('Archive refused: unreadable ' . self::DATA_FILE);
        try {
            $content = (string)stream_get_contents($stream, self::MAX_DATA_SIZE + 1);
        } finally {
            fclose($stream);
        }
        if (strlen($content) > self::MAX_DATA_SIZE) {
            throw new \InvalidArgumentException('Archive refused: ' . self::DATA_FILE . ' bigger than ' . (self::MAX_DATA_SIZE >> 20) . ' MB');
        }

        return $content;
    }

    /**
     * Copies an entry of the archive to $target by chunks, counting the bytes really written: the sizes declared in
     * the index of the archive may be false (zip bomb).
     */
    private function extract(\ZipArchive $zip, int $index, string $target, int &$size): void
    {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0o775, true);
        }

        $in = $zip->getStreamIndex($index) ?: throw new \InvalidArgumentException('Archive refused: unreadable entry ' . $index);
        // content written to a regular file: a symbolic link of the archive is not recreated
        $out = fopen($target, 'wb') ?: throw new \RuntimeException('Cannot write ' . $target);
        try {
            while (!feof($in)) {
                $chunk = (string)fread($in, 1 << 20);
                $size += strlen($chunk);
                if ($size > self::MAX_SIZE) {
                    throw new \InvalidArgumentException('Archive refused: more than ' . (self::MAX_SIZE >> 20) . ' MB once uncompressed');
                }
                fwrite($out, $chunk) === strlen($chunk) ?: throw new \RuntimeException('Cannot write ' . $target);
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /**
     * Each file of the archive is a file of a template of the archive, or a file of the library.
     *
     * @param array<int, string> $entries
     * @param list<string> $paths paths of the templates of the archive
     */
    private function checkEntries(array $entries, array $paths): void
    {
        $extension = CruditReportGenerator::EXTENSION;
        foreach ($entries as $relative) {
            $model = in_array($relative, $paths, true)
                || (str_ends_with($relative, $extension) && in_array(substr($relative, 0, -strlen($extension)), $paths, true));
            if (!$model && !$this->isLibraryFile($relative)) {
                throw new \InvalidArgumentException('Archive refused: ' . self::FILES_DIR . $relative . ' is not a file of a template nor of the library');
            }
        }
    }

    /**
     * The rows must be objects of scalar values. The columns that the table does not have are collected: they are
     * ignored when writing.
     *
     * @param array{rows: array<mixed>} $data
     *
     * @return array{0: list<string>, 1: list<string>} columns of the table, ignored columns
     */
    private function checkRows(array $data): array
    {
        [$table] = $this->table();
        $schema = $this->em->getConnection()->createSchemaManager();
        // introspectTableColumnsByUnquotedName() since DBAL 4.3, listTableColumns() (deprecated) before
        // @phpstan-ignore function.alreadyNarrowedType
        $columns = method_exists($schema, 'introspectTableColumnsByUnquotedName')
            ? array_map(fn (Column $c): string => $c->getObjectName()->getIdentifier()->getValue(), $schema->introspectTableColumnsByUnquotedName($table))
            // @phpstan-ignore method.deprecated
            : array_keys($schema->listTableColumns($table));
        $known = array_map('strtolower', $columns);
        $pathColumn = strtolower($this->pathColumn());
        $ignored = [];

        foreach ($data['rows'] as $n => $row) {
            if (!is_array($row) || $row === [] || array_is_list($row)) {
                throw new \InvalidArgumentException('Archive refused: unreadable row ' . ($n + 1));
            }
            foreach ($row as $column => $value) {
                if (!in_array(strtolower((string)$column), $known, true)) {
                    $ignored[(string)$column] = true;
                }
                if (!is_scalar($value) && $value !== null) {
                    throw new \InvalidArgumentException('Archive refused: unreadable value for "' . $column . '", row ' . ($n + 1));
                }
                // a path outside the templates folder would be served by the download of the template
                if (strtolower((string)$column) === $pathColumn && is_string($value)) {
                    foreach (PdfGenerator::paths($value) as $path) {
                        // an empty path (template without file) only fails when generating
                        if ($path !== '' && !PdfGenerator::isRelativePath($path)) {
                            throw new \InvalidArgumentException('Archive refused: path "' . $value . '" outside the templates folder, row ' . ($n + 1));
                        }
                    }
                }
            }
        }

        return [$columns, array_keys($ignored)];
    }

    /**
     * Replaces the rows of the table (transaction) and the files of the templates: the files of the old templates, the
     * library folders and the files of the target that the archive would overwrite are set aside, then deleted once
     * the transaction is committed. On failure they are put back; those that cannot be put back are kept in the
     * hidden backup folder, named by the exception.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     */
    private function replace(string $root, string $staging, array $rows, array $columns): void
    {
        $conn = $this->em->getConnection();
        [$table] = $this->table();
        $current = $conn->fetchAllAssociative('SELECT ' . $this->quoteName($this->pathColumn()) . ' FROM ' . $this->quoteName($table));
        $incoming = $this->items($staging, $this->modelFiles($staging, $rows));
        $old = array_values(array_unique([
            ...$this->items($root, $this->modelFiles($root, $current)),
            ...array_filter($incoming, fn (string $item): bool => file_exists($root . '/' . $item) || is_link($root . '/' . $item)),
        ]));

        $backup = $root . '/.backup-' . bin2hex(random_bytes(6));
        mkdir($backup, 0o775);
        $moved = [];
        $added = [];
        $keepBackup = false;

        $conn->beginTransaction();
        try {
            $conn->executeStatement('DELETE FROM ' . $this->quoteName($table));
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $column => $value) {
                    $name = $this->column($columns, (string)$column);
                    if ($name !== null) {
                        $values[$this->quoteName($name)] = $value;
                    }
                }
                $conn->insert($this->quoteName($table), $values);
            }

            foreach ($old as $item) {
                $this->move($root . '/' . $item, $backup . '/' . $item) ?: throw new \RuntimeException('Cannot move ' . $item);
                $moved[] = $item;
            }
            foreach ($incoming as $item) {
                $this->move($staging . '/' . $item, $root . '/' . $item) ?: throw new \RuntimeException('Cannot move ' . $item);
                $added[] = $item;
            }

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            foreach ($added as $item) {
                $this->remove($root . '/' . $item);
            }
            $lost = array_values(array_filter($moved, fn (string $item): bool => !$this->move($backup . '/' . $item, $root . '/' . $item)));
            if ($lost) {
                $keepBackup = true;

                throw new ImportBackupException($lost, $backup, $e);
            }

            throw $e;
        } finally {
            if (!$keepBackup) {
                $this->remove($backup);
            }
        }

        $this->em->clear();
    }

    /** Moves a file or a folder, creating the parent folder of the target. */
    private function move(string $from, string $to): bool
    {
        if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0o775, true)) {
            return false;
        }

        return @rename($from, $to);
    }

    /**
     * Equivalent .sql file: empties the table then inserts the rows (MySQL / MariaDB).
     *
     * @param list<array<string, mixed>> $rows
     */
    private function sql(Connection $conn, string $table, array $rows): string
    {
        $quoted = $this->quoteName($table);
        $sql = '-- PDF templates exported on ' . date('Y-m-d H:i') . "\n"
            . "-- Copy the pdfmodel/ folder of the archive into the templates folder (lle_pdf_generator.path)\n\n"
            . 'DELETE FROM ' . $quoted . ";\n";

        foreach ($rows as $row) {
            $sql .= 'INSERT INTO ' . $quoted
                . ' (' . implode(', ', array_map($this->quoteName(...), array_keys($row))) . ')'
                . ' VALUES (' . implode(', ', array_map(
                    fn (mixed $v): string => match (true) {
                        $v === null => 'NULL',
                        is_int($v), is_float($v) => (string)$v,
                        is_bool($v) => $v ? '1' : '0',
                        default => $conn->quote((string)$v),
                    },
                    array_values($row)
                )) . ");\n";
        }

        return $sql;
    }

    private function checkAvailable(): void
    {
        if (!self::isAvailable()) {
            throw new \LogicException('The zip PHP extension is required to export or import the PDF templates');
        }
    }

    /** Quoted table or column name (Connection::quoteIdentifier() is deprecated since DBAL 4.3). */
    private function quoteName(string $name): string
    {
        return $this->em->getConnection()->getDatabasePlatform()->quoteSingleIdentifier($name);
    }

    /** @return array{0: string, 1: string} table and identifier column of the templates */
    private function table(): array
    {
        /** @var class-string $class */
        $class = $this->pdfGenerator->getRepository()->getClassName();
        $meta = $this->em->getClassMetadata($class);

        return [$meta->getTableName(), $meta->getSingleIdentifierColumnName()];
    }

    /** Column of the file path of a template (PdfModel::path). */
    private function pathColumn(): string
    {
        /** @var class-string $class */
        $class = $this->pdfGenerator->getRepository()->getClassName();
        $meta = $this->em->getClassMetadata($class);

        return $meta->hasField('path') ? $meta->getColumnName('path') : 'path';
    }

    /**
     * Files named by the path column of the rows (several resources separated by commas, .template.json implied).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<string>
     */
    private function modelPaths(array $rows): array
    {
        $column = strtolower($this->pathColumn());
        $paths = [];
        foreach ($rows as $row) {
            foreach ($row as $name => $value) {
                if (strtolower((string)$name) === $column && is_string($value)) {
                    foreach (PdfGenerator::paths($value) as $path) {
                        if ($this->safePath($path)) {
                            $paths[] = $path;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Existing files of the templates of $rows in $root.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<string>
     */
    private function modelFiles(string $root, array $rows): array
    {
        $files = [];
        foreach ($this->modelPaths($rows) as $path) {
            foreach ([$path, $path . CruditReportGenerator::EXTENSION] as $candidate) {
                if (is_file($root . '/' . $candidate) && !is_link($root . '/' . $candidate)) {
                    $files[] = $candidate;
                }
            }
        }

        return array_values(array_unique($files));
    }

    private function isLibraryFile(string $relative): bool
    {
        return in_array(explode('/', $relative)[0], self::LIBRARY_DIRS, true);
    }

    /**
     * Elements to move as a whole: the files of the templates and the library folders that exist in $dir.
     *
     * @param list<string> $modelFiles
     *
     * @return list<string>
     */
    private function items(string $dir, array $modelFiles): array
    {
        $items = array_values(array_filter($modelFiles, fn (string $file): bool => !$this->isLibraryFile($file)));
        foreach (self::LIBRARY_DIRS as $library) {
            if (is_dir($dir . '/' . $library)) {
                $items[] = $library;
            }
        }

        return $items;
    }

    /**
     * Exact name of the column (case of the table), or null if the table does not have it.
     *
     * @param list<string> $columns
     */
    private function column(array $columns, string $column): ?string
    {
        foreach ($columns as $c) {
            if (strtolower($c) === strtolower($column)) {
                return $c;
            }
        }

        return null;
    }

    /** Relative path without "..", absolute path or hidden element (.import-, .backup-…). */
    private function safePath(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, '\\') || str_contains($relative, "\0") || str_starts_with($relative, '/')) {
            return false;
        }

        foreach (explode('/', $relative) as $part) {
            if ($part === '' || $part === '..' || str_starts_with($part, '.')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Files to export, relative to $root: those of the templates and those of the library, without the hidden elements.
     *
     * @param list<string> $modelFiles
     *
     * @return list<string>
     */
    private function files(string $root, array $modelFiles): array
    {
        $files = $modelFiles;
        foreach (self::LIBRARY_DIRS as $library) {
            if (!is_dir($root . '/' . $library) || is_link($root . '/' . $library)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root . '/' . $library, \FilesystemIterator::SKIP_DOTS),
                fn (\SplFileInfo $f): bool => !str_starts_with($f->getFilename(), '.')
            ));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                }
            }
        }
        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $name) {
                if ($name !== '.' && $name !== '..') {
                    $this->remove($path . '/' . $name);
                }
            }
            rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}
