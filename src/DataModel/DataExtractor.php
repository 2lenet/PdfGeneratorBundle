<?php

namespace Lle\PdfGeneratorBundle\DataModel;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Extracts the data of a crudit_report template following its parameters: each field is read (array key, object
 * property or getter) then converted according to its type. The result matches the parameters: Doctrine decimals
 * (strings) to numbers, dates to the day, image paths to data URIs. Computed parameters are skipped.
 */
class DataExtractor
{
    private const IMAGE_TYPES = [
        IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_GIF => 'image/gif',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    private PropertyAccessorInterface $accessor;

    public function __construct()
    {
        $this->accessor = PropertyAccess::createPropertyAccessorBuilder()->getPropertyAccessor();
    }

    /**
     * @param list<array<string, mixed>> $parameters
     */
    public function extract(array $parameters, mixed $data): \stdClass
    {
        $out = new \stdClass();
        foreach ($parameters as $parameter) {
            if (isset($parameter['expression'])) {
                continue;
            }
            $name = $parameter['name'];
            $out->$name = $this->convert($parameter, $this->read($data, $name));
        }

        return $out;
    }

    private function read(mixed $data, string $name): mixed
    {
        if (is_array($data)) {
            return $data[$name] ?? null;
        }
        if ($data instanceof \ArrayAccess) {
            return $data[$name] ?? null;
        }
        if (is_object($data) && $this->accessor->isReadable($data, $name)) {
            return $this->accessor->getValue($data, $name);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $parameter
     */
    private function convert(array $parameter, mixed $value): mixed
    {
        if ($value === null) {
            return $parameter['type'] === 'array' ? [] : null;
        }

        return match ($parameter['type']) {
            'object' => is_array($value) || is_object($value) ? $this->extract($parameter['children'] ?? [], $value) : null,
            'array' => is_iterable($value) ? $this->items($parameter['children'] ?? [], $value) : [],
            'string' => $this->string($value),
            'number' => is_numeric($value) ? $value + 0 : null,
            'integer' => is_numeric($value) ? (int)$value : null,
            'boolean' => is_scalar($value) ? (bool)$value : null,
            'date' => $this->date($value, 'Y-m-d'),
            'datetime' => $this->date($value, \DateTimeInterface::RFC3339),
            'image' => is_string($value) ? $this->image($value) : null,
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $children
     * @param iterable<mixed> $value
     *
     * @return list<\stdClass>
     */
    private function items(array $children, iterable $value): array
    {
        $items = [];
        foreach ($value as $item) {
            $items[] = $this->extract($children, $item);
        }

        return $items;
    }

    private function string(mixed $value): ?string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value), $value instanceof \Stringable => (string)$value,
            $value instanceof \DateTimeInterface => $value->format('d/m/Y'),
            $value instanceof \BackedEnum => (string)$value->value,
            $value instanceof \UnitEnum => $value->name,
            default => null,
        };
    }

    private function date(mixed $value, string $format): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return $format === 'Y-m-d' ? substr($value, 0, 10) : $value;
        }

        return null;
    }

    /** Data URIs and URLs are kept; an image file path is converted to a data URI. */
    private function image(string $value): ?string
    {
        if (str_starts_with($value, 'data:') || preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if (!is_file($value) || !is_readable($value)) {
            return null;
        }

        $info = @getimagesize($value);
        $mime = is_array($info) ? (self::IMAGE_TYPES[$info[2]] ?? null) : null;
        if ($mime === null && preg_match('/<svg[\s>]/i', (string)file_get_contents($value, false, null, 0, 1024))) {
            $mime = 'image/svg+xml';
        }

        return $mime ? 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($value)) : null;
    }
}
