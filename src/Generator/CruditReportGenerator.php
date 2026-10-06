<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Lle\PdfGeneratorBundle\DataModel\DataModelRegistry;
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

    /** generate() option: data source of the template (PdfModel::datasource), passed by PdfGenerator. */
    public const OPTION_DATASOURCE = 'crudit_datasource';

    /** generate() option: code of the template (PdfModel::code), passed by PdfGenerator for the error messages. */
    public const OPTION_MODEL = 'crudit_model';

    public function __construct(
        protected NormalizerInterface $normalizer,
        protected ParameterBagInterface $parameterBag,
        protected DataModelRegistry $dataModels,
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
        $name = (string)($options[self::OPTION_MODEL] ?? basename($source));
        $templateFile = null;
        $dataFile = null;

        try {
            if (is_string($datasource) && $datasource !== '') {
                if (!$this->dataModels->has($datasource)) {
                    throw new \RuntimeException(sprintf(
                        'PDF GENERATOR ERROR: the template %s uses the data source "%s", which does not exist (renamed or removed?): choose another one in the templates screen',
                        $name,
                        $datasource,
                    ));
                }
                // Data source: its current parameters replace the copy in the template (rendering always follows the
                // PHP), and the data is extracted according to them; without data, the sample of the data source.
                $template = $this->applyDataSource(
                    json_decode((string)file_get_contents($source), false, 512, JSON_THROW_ON_ERROR),
                    $datasource,
                );
                $data = $this->isEmpty($params) ? $this->dataModels->getSample($datasource) : $this->dataModels->extract($datasource, $params);
                $templateFile = $this->tempFile($this->encode($template));
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
                $templateFile ?? $source,
            ];

            // Without data ("Show the PDF" preview of the admin), crudit renders the template with its testData.
            if (!empty($data)) {
                $dataFile = $this->tempFile(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $command[] = $dataFile;
            }

            $process = new Process($command);
            $process->setTimeout($this->parameterBag->get('lle.pdf.crudit.timeout'));
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(
                    'PDF GENERATOR ERROR: crudit render ' . $name . ': ' . $this->errorMessage($process)
                );
            }
        } finally {
            foreach ([$dataFile, $templateFile] as $file) {
                if ($file !== null) {
                    unlink($file);
                }
            }
        }
    }

    /** Temporary file holding $content (to delete by the caller). */
    private function tempFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'crudit') ?: throw new \RuntimeException('Cannot create a temporary file');
        if (file_put_contents($file, $content) !== strlen($content)) {
            unlink($file);

            throw new \RuntimeException('Cannot write the temporary file ' . $file);
        }

        return $file;
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

    /** @param \stdClass|array<string, mixed> $template */
    public function encode(\stdClass|array $template): string
    {
        return json_encode(
            $template,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        ) . "\n";
    }

    /**
     * No data at all ("Show the PDF" action of the admin: []). A real data set whose values are all empty is not
     * "no data": it must not be replaced by the sample, which holds the data of another document.
     *
     * @param iterable<mixed> $params
     */
    private function isEmpty(iterable $params): bool
    {
        foreach ($params as $value) {
            return false;
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
        $file = $this->tempFile($json);

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
        $json = $this->encode($template);
        if (file_put_contents(rtrim($dir, '/') . '/' . $fileName, $json) !== strlen($json)) {
            throw new \RuntimeException('Cannot write the template ' . $fileName . ' in ' . $dir);
        }

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
