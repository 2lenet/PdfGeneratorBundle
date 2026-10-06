<?php

namespace Lle\PdfGeneratorBundle\Transfer;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;

/**
 * Copies the PDF templates from one platform to another (local ↔ production): a zip archive holds the rows of the
 * templates table (pdfmodel.json, read on import, and pdfmodel.sql, to read or import by hand) and the templates
 * folder (pdfmodel/: .docx and .template.json files, images and fonts of the library).
 *
 * Import replaces everything: the table is emptied then filled with the rows of the archive (ids kept), and the
 * templates folder is replaced by the one of the archive. The .sql file is never executed.
 */
class ModelTransfer
{
    public const DATA_FILE = 'pdfmodel.json';

    public const SQL_FILE = 'pdfmodel.sql';

    public const FILES_DIR = 'pdfmodel/';

    public const FORMAT = 1;

    /** Maximum uncompressed size of the archive, and maximum number of files. */
    public const MAX_SIZE = 512 << 20;

    public const MAX_FILES = 10000;

    public function __construct(
        private EntityManagerInterface $em,
        private PdfGenerator $pdfGenerator,
    ) {
    }

    /** Creates the archive in a temporary file and returns its path (to delete once sent). */
    public function export(): string
    {
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
        foreach ($this->files($root) as $relative) {
            $zip->addFile($root . '/' . $relative, self::FILES_DIR . $relative);
        }

        if (!$zip->close()) {
            throw new \RuntimeException('Cannot write the archive');
        }

        return $file;
    }

    /**
     * Imports an archive made by export(): replaces the rows of the table and the templates folder.
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
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::RDONLY) !== true) {
            throw new \InvalidArgumentException('Unreadable zip archive');
        }

        try {
            [$data, $entries] = $this->readArchive($zip);
            [$columns, $ignored] = $this->checkRows($data);

            $root = rtrim($this->pdfGenerator->getPath(), '/');
            $staging = $root . '/.import-' . bin2hex(random_bytes(6));
            mkdir($staging, 0o775, true);
            try {
                foreach ($entries as $index => $relative) {
                    $target = $staging . '/' . $relative;
                    if (!is_dir(dirname($target))) {
                        mkdir(dirname($target), 0o775, true);
                    }
                    // content written to a regular file: a symbolic link of the archive is not recreated
                    file_put_contents($target, (string)$zip->getFromIndex($index));
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
                $data = json_decode((string)$zip->getFromIndex($i), true);
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
        $columns = array_keys($this->em->getConnection()->createSchemaManager()->listTableColumns($table));
        $known = array_map('strtolower', $columns);
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
            }
        }

        return [$columns, array_keys($ignored)];
    }

    /**
     * Replaces the rows of the table (transaction) and the content of the templates folder. The old files are set
     * aside, then deleted once the transaction is committed; on failure they are put back.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     */
    private function replace(string $root, string $staging, array $rows, array $columns): void
    {
        $conn = $this->em->getConnection();
        [$table] = $this->table();
        $backup = $root . '/.backup-' . bin2hex(random_bytes(6));
        mkdir($backup, 0o775);
        $moved = [];
        $added = [];

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

            foreach ($this->entries($root) as $name) {
                rename($root . '/' . $name, $backup . '/' . $name) ?: throw new \RuntimeException('Cannot move ' . $name);
                $moved[] = $name;
            }
            foreach ($this->entries($staging) as $name) {
                rename($staging . '/' . $name, $root . '/' . $name) ?: throw new \RuntimeException('Cannot move ' . $name);
                $added[] = $name;
            }

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            foreach ($added as $name) {
                $this->remove($root . '/' . $name);
            }
            foreach ($moved as $name) {
                rename($backup . '/' . $name, $root . '/' . $name);
            }
            throw $e;
        } finally {
            $this->remove($backup);
        }

        $this->em->clear();
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
     * Files of the templates folder, relative to $root, without the hidden elements.
     *
     * @return list<string>
     */
    private function files(string $root): array
    {
        $files = [];
        if (!is_dir($root)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            fn (\SplFileInfo $f): bool => !str_starts_with($f->getFilename(), '.')
        ));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
        sort($files);

        return $files;
    }

    /**
     * First-level elements of a folder, without the hidden elements.
     *
     * @return list<string>
     */
    private function entries(string $dir): array
    {
        return array_values(array_filter(scandir($dir) ?: [], fn (string $n): bool => !str_starts_with($n, '.')));
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
