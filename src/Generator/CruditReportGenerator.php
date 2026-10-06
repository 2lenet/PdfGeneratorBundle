<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Lle\PdfGeneratorBundle\DataModel\DataModelRegistry;
use Lle\PdfGeneratorBundle\Exception\LibraryFileExistsException;
use Lle\PdfGeneratorBundle\Exception\ModelNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Crudit Report templates (.template.json): the data is normalized to JSON
 * then rendered to PDF by the crudit command (template → Typst → PDF).
 */
class CruditReportGenerator extends AbstractPdfGenerator
{
    public const OPTION_GROUPS = 'crudit_groups';

    public const EXTENSION = '.template.json';

    /** Files of the template library (asset refs and fonts readable by Typst): extension → kind. */
    public const LIBRARY_EXTENSIONS = [
        'png' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'gif' => 'image', 'svg' => 'image', 'webp' => 'image',
        'ttf' => 'font', 'otf' => 'font',
    ];

    public const LIBRARY_MAX_SIZE = 10 << 20;

    /** generate() option: data source of the template (PdfModel::datasource), passed by PdfGenerator. */
    public const OPTION_DATASOURCE = 'crudit_datasource';

    public function __construct(
        private NormalizerInterface $normalizer,
        private ParameterBagInterface $parameterBag,
        private DataModelRegistry $dataModels,
    ) {
    }

    public static function getName(): string
    {
        return 'crudit_report';
    }

    public function generate(string $source, iterable $params, string $savePath, array $options = []): void
    {
        $source = $this->resolveSource($source);
        $datasource = $options[self::OPTION_DATASOURCE] ?? null;
        $templateFile = null;

        if (is_string($datasource) && $datasource !== '') {
            // Data source: its current parameters replace the copy in the template (rendering always follows the
            // PHP), and the data is extracted according to them; without data, the sample of the data source.
            $template = $this->applyDataSource(
                json_decode((string)file_get_contents($source), false, 512, JSON_THROW_ON_ERROR),
                $datasource,
            );
            $templateFile = tempnam(sys_get_temp_dir(), 'crudit');
            file_put_contents($templateFile, $this->encode($template));
            $source = $templateFile;
            $data = $this->isEmpty($params) ? $this->dataModels->getSample($datasource) : $this->dataModels->extract($datasource, $params);
        } else {
            $template = $this->readTemplate($source);
            $data = $this->normalizer->normalize($params, 'json', [
                'groups' => $options[self::OPTION_GROUPS] ?? ['pdfgenerator'],
                DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::RFC3339,
            ]);
            $data = $this->fitDates($template['parameters'] ?? [], $data);
        }

        $this->checkInstallation();
        $command = [
            $this->parameterBag->get('lle.pdf.crudit.bin'),
            'render',
            '-json',
            '-o', $savePath,
            '-typst', $this->parameterBag->get('lle.pdf.crudit.typst'),
            '-assets', $this->pdfPath,
            '-allow', implode(',', $this->parameterBag->get('lle.pdf.crudit.allowed_hosts')),
            $source,
        ];

        // Without data ("Show the PDF" preview of the admin), crudit renders the template with its testData.
        $dataFile = null;
        if (!empty($data)) {
            $dataFile = tempnam(sys_get_temp_dir(), 'crudit');
            file_put_contents(
                $dataFile,
                json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $command[] = $dataFile;
        }

        try {
            $process = new Process($command);
            $process->setTimeout($this->parameterBag->get('lle.pdf.crudit.timeout'));
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(
                    'PDF GENERATOR ERROR: crudit render ' . basename($source) . ': ' . $this->errorMessage($process)
                );
            }
        } finally {
            if ($dataFile) {
                unlink($dataFile);
            }
            if ($templateFile) {
                unlink($templateFile);
            }
        }
    }

    /**
     * Puts the parameters of the data source in the template (JSON object decoded without associative arrays, to
     * keep empty {}): they replace the provided parameters; the computed parameters of the template are kept after.
     * designer.source tells the designer the data source, whose parameters it shows read-only.
     */
    public function applyDataSource(\stdClass $template, string $datasource): \stdClass
    {
        $parameters = $this->dataModels->getParameters($datasource);
        $names = array_column($parameters, 'name');
        $computed = array_values(array_filter(
            (array)($template->parameters ?? []),
            fn (mixed $p): bool => $p instanceof \stdClass && isset($p->expression) && !in_array($p->name ?? null, $names, true),
        ));
        $template->parameters = [...json_decode(json_encode($parameters, JSON_THROW_ON_ERROR), false), ...$computed];

        $designer = ($template->designer ?? null) instanceof \stdClass ? $template->designer : new \stdClass();
        $designer->source = $datasource;
        $template->designer = $designer;

        return $template;
    }

    /** Removes designer.source from a template that no longer has a data source. */
    public function removeDataSource(\stdClass $template): \stdClass
    {
        if (($template->designer ?? null) instanceof \stdClass) {
            unset($template->designer->source);
            if (!get_object_vars($template->designer)) {
                unset($template->designer);
            }
        }

        return $template;
    }

    /** Sample of the data source, extracted according to its parameters; null: no sample. */
    public function getDataSourceSample(string $datasource): ?\stdClass
    {
        return $this->dataModels->getSample($datasource);
    }

    public function encode(\stdClass $template): string
    {
        return json_encode(
            $template,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        ) . "\n";
    }

    /**
     * No data: [] or [[]] ("Show the PDF" action of the admin).
     *
     * @param iterable<mixed> $params
     */
    private function isEmpty(iterable $params): bool
    {
        foreach ($params as $value) {
            if ($value !== null && $value !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Paths declared in the "parameters" of the template;
     * the fields of an array are written lines[].label.
     */
    public function getVariables(string $source): array
    {
        $res = [];
        $walk = function (array $params, string $prefix) use (&$walk, &$res): void {
            foreach ($params as $param) {
                $path = $prefix . $param['name'];
                if (isset($param['children'])) {
                    $walk($param['children'], $path . ($param['type'] === 'array' ? '[].' : '.'));
                } else {
                    $res[$path] = 1;
                }
            }
        };
        $walk($this->readTemplate($this->resolveSource($source))['parameters'] ?? [], '');

        return $res;
    }

    /**
     * Validates a template with crudit validate (shape and integrity).
     *
     * @return array{ok: bool, errors: list<array{code: string, path?: string, message: string}>, warnings: list<array{code: string, path?: string, message: string}>}
     */
    public function validate(string $json): array
    {
        $this->checkInstallation();
        $file = tempnam(sys_get_temp_dir(), 'crudit');
        file_put_contents($file, $json);

        try {
            $process = new Process([$this->parameterBag->get('lle.pdf.crudit.bin'), 'validate', '-json', $file]);
            $process->setTimeout(30);
            $process->run();
        } finally {
            unlink($file);
        }

        $lines = array_filter(explode("\n", trim($process->getOutput())));
        $result = $lines ? json_decode((string)end($lines), true) : null;

        if (!is_array($result) || !isset($result['ok'])) {
            throw new \RuntimeException('PDF GENERATOR ERROR: crudit validate: ' . (trim($process->getErrorOutput()) ?: 'exit code ' . $process->getExitCode()));
        }

        return ['ok' => (bool)$result['ok'], 'errors' => $result['errors'] ?? [], 'warnings' => $result['warnings'] ?? []];
    }

    /**
     * Creates an empty template (A4 page, no element) in $dir, to edit in the designer afterwards.
     * Returns the file name, to store in PdfModel::path.
     */
    public function createTemplate(string $dir, string $name): string
    {
        $id = str_replace('.', '', uniqid('', true));
        $template = $this->emptyTemplate('tpl_' . $id, $name !== '' ? mb_substr($name, 0, 200) : 'New template');

        $fileName = $id . self::EXTENSION;
        file_put_contents(
            rtrim($dir, '/') . '/' . $fileName,
            json_encode($template, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );

        return $fileName;
    }

    /**
     * Empty template: A4 page, base style, no parameter nor element.
     *
     * @return array<string, mixed>
     */
    private function emptyTemplate(string $id, string $name): array
    {
        return [
            'formatVersion' => '1.0',
            'id' => $id,
            'name' => $name,
            'locale' => ['language' => 'fr-FR', 'currency' => 'EUR', 'timezone' => 'Europe/Paris'],
            'page' => [
                'size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15],
            ],
            'defaultStyleId' => 'st_base',
            'styles' => ['st_base' => ['name' => 'Base', 'fontSize' => 10, 'color' => '#1F2937']],
            'parameters' => [],
            'testData' => new \stdClass(),
            'bands' => ['content' => ['children' => []]],
            'elements' => new \stdClass(),
        ];
    }

    /**
     * Files of the template library ($dir and its subfolders) usable in a template: images (with their
     * dimensions, except SVG) and fonts, sorted by URI.
     *
     * @return list<array{uri: string, kind: string, size: int, modified: string, widthPx?: int, heightPx?: int}>
     */
    public function listLibraryFiles(string $dir): array
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
            $kind = self::LIBRARY_EXTENSIONS[strtolower($file->getExtension())] ?? null;
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
    public function storeLibraryFile(string $dir, string $file, string $originalName, bool $overwrite = false): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $kind = self::LIBRARY_EXTENSIONS[$extension] ?? null;

        if ($kind === null) {
            throw new \InvalidArgumentException(
                $originalName . ': extension refused (' . implode(', ', array_keys(self::LIBRARY_EXTENSIONS)) . ')'
            );
        }
        if (filesize($file) > self::LIBRARY_MAX_SIZE) {
            throw new \InvalidArgumentException(
                $originalName . ': file too big (' . (self::LIBRARY_MAX_SIZE >> 20) . ' MB max)'
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

        if (self::LIBRARY_EXTENSIONS[$extension] === 'font') {
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

    private function resolveSource(string $source): string
    {
        if (file_exists($source)) {
            return $source;
        }

        if (file_exists($source . self::EXTENSION)) {
            return $source . self::EXTENSION;
        }

        throw new ModelNotFoundException($source . '(' . self::EXTENSION . ') not found');
    }

    private function readTemplate(string $source): array
    {
        return json_decode((string)file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Date parameters expect YYYY-MM-DD: the dates normalized as RFC 3339
     * (DateTime of an entity) are cut to their day.
     */
    private function fitDates(array $params, mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($params as $param) {
            $name = $param['name'];
            if (!array_key_exists($name, $data)) {
                continue;
            }

            if ($param['type'] === 'date' && is_string($data[$name]) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $data[$name])) {
                $data[$name] = substr($data[$name], 0, 10);
            } elseif ($param['type'] === 'object') {
                $data[$name] = $this->fitDates($param['children'] ?? [], $data[$name]);
            } elseif ($param['type'] === 'array' && is_array($data[$name])) {
                foreach ($data[$name] as $k => $item) {
                    $data[$name][$k] = $this->fitDates($param['children'] ?? [], $item);
                }
            }
        }

        return $data;
    }

    /**
     * The crudit and Typst binaries are installed by the project: a clear message when one is missing, rather than the
     * error of the shell.
     */
    private function checkInstallation(): void
    {
        foreach (['bin' => 'crudit', 'typst' => 'Typst'] as $option => $label) {
            $bin = (string)$this->parameterBag->get('lle.pdf.crudit.' . $option);
            $found = str_contains($bin, '/') ? is_file($bin) && is_executable($bin) : (new ExecutableFinder())->find($bin) !== null;
            if (!$found) {
                throw new \RuntimeException(sprintf(
                    'PDF GENERATOR ERROR: %s binary "%s" not found (lle_pdf_generator.crudit.%s), see the crudit_report installation',
                    $label,
                    $bin,
                    $option,
                ));
            }
        }
    }

    /** Render diagnostics (-json output) or, failing that, crudit error output. */
    private function errorMessage(Process $process): string
    {
        $lines = array_filter(explode("\n", trim($process->getOutput())));
        $result = $lines ? json_decode((string)end($lines), true) : null;

        if (is_array($result) && !empty($result['errors'])) {
            return implode('; ', array_map(
                fn (array $e): string => $e['code'] . ' ' . (isset($e['path']) ? $e['path'] . ': ' : '') . $e['message'],
                $result['errors']
            ));
        }

        return trim($process->getErrorOutput()) ?: 'exit code ' . $process->getExitCode();
    }
}
