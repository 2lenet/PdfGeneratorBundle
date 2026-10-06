<?php

namespace Lle\PdfGeneratorBundle\DataModel;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditFields;
use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditImage;
use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditLabel;
use Lle\PdfGeneratorBundle\DataModel\Attribute\CruditType;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the parameters of a crudit_report template (crudit-report docs/06) from the data class of a data source:
 * each of its typed public properties is a parameter, in declaration order.
 *
 * - string, int, float, bool, DateTimeInterface: values; CruditImage and CruditType attributes to refine;
 * - entity (Doctrine mapping or Serializer group): object whose fields are those of the group (pdfgenerator by
 *   default) or those of CruditFields; their type comes from the Doctrine mapping, otherwise from the PHP type;
 *   relations are followed up to maxDepth levels, without going back to an already visited class (cycles);
 * - other class: object described the same way as the data class (its typed public properties);
 * - array, iterable, Collection: list, whose item type comes from the PHPDoc (@var list<Class>).
 */
final class DataModelBuilder
{
    public const TYPES = ['string', 'number', 'integer', 'boolean', 'date', 'datetime', 'image'];

    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private const DOCTRINE_TYPES = [
        'integer' => 'integer', 'smallint' => 'integer', 'bigint' => 'integer',
        'decimal' => 'number', 'float' => 'number', 'smallfloat' => 'number',
        'boolean' => 'boolean',
        'date' => 'date', 'date_immutable' => 'date',
        'datetime' => 'datetime', 'datetime_immutable' => 'datetime',
        'datetimetz' => 'datetime', 'datetimetz_immutable' => 'datetime',
    ];

    /**
     * @param list<string> $groups
     */
    public function __construct(
        private ClassMetadataFactoryInterface $serializerMetadata,
        private ?ManagerRegistry $doctrine = null,
        private ?TranslatorInterface $translator = null,
        private ?PropertyInfoExtractorInterface $propertyInfo = null,
        private array $groups = ['pdfgenerator'],
        private int $maxDepth = 3,
    ) {
    }

    /**
     * Parameters described by a data class.
     *
     * @param class-string $dataClass
     *
     * @return list<array<string, mixed>>
     *
     * @throws \InvalidArgumentException untyped property, unknown list type, invalid attribute, cycle
     */
    public function describe(string $dataClass): array
    {
        return $this->dataClassFields($dataClass, [$dataClass]);
    }

    /**
     * @param class-string $class
     * @param list<class-string> $stack
     *
     * @return list<array<string, mixed>>
     */
    private function dataClassFields(string $class, array $stack): array
    {
        $parameters = [];
        foreach ((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $parameters[] = $this->dataProperty($class, $property, $stack);
            }
        }

        return $parameters;
    }

    /**
     * @param class-string $class
     * @param list<class-string> $stack
     *
     * @return array<string, mixed>
     */
    private function dataProperty(string $class, \ReflectionProperty $property, array $stack): array
    {
        $name = $property->getName();
        $where = $class . '::$' . $name;
        if (!preg_match(self::IDENTIFIER, $name)) {
            throw new \InvalidArgumentException($where . ': invalid parameter name');
        }
        $label = $this->attribute($property, CruditLabel::class)?->label ?? $this->label($name);
        $fields = $this->attribute($property, CruditFields::class)?->fields;

        $forced = $this->attribute($property, CruditImage::class) ? 'image' : $this->attribute($property, CruditType::class)?->type;
        if ($forced !== null) {
            if (!in_array($forced, self::TYPES, true)) {
                throw new \InvalidArgumentException($where . ': unknown type ' . $forced . ' (' . implode(', ', self::TYPES) . ')');
            }

            return $this->parameter($name, $forced, $label);
        }

        $type = $property->getType();
        if (!$type instanceof \ReflectionNamedType || $type->getName() === 'mixed') {
            throw new \InvalidArgumentException($where . ': PHP type required (string, int, a class, list<Class>…)');
        }
        $php = $type->getName();

        if ($type->isBuiltin()) {
            $scalar = ['int' => 'integer', 'float' => 'number', 'bool' => 'boolean', 'string' => 'string'][$php] ?? null;
            if ($scalar !== null) {
                return $this->parameter($name, $scalar, $label);
            }
            if ($php !== 'array' && $php !== 'iterable') {
                throw new \InvalidArgumentException($where . ': unsupported type ' . $php);
            }

            return $this->classParameter($name, 'array', $this->itemClass($class, $name, $where), $fields, $label, $stack);
        }

        /** @var class-string $php */
        if (is_a($php, \DateTimeInterface::class, true)) {
            return $this->parameter($name, 'datetime', $label);
        }
        if (is_a($php, \BackedEnum::class, true) || is_a($php, \UnitEnum::class, true)) {
            return $this->parameter($name, 'string', $label);
        }
        if (is_a($php, \Traversable::class, true)) {
            return $this->classParameter($name, 'array', $this->itemClass($class, $name, $where), $fields, $label, $stack);
        }

        return $this->classParameter($name, 'object', $php, $fields, $label, $stack);
    }

    /**
     * Object or list of $class objects: entity (fields of the group or $fields), otherwise data class.
     *
     * @param class-string $class
     * @param list<string>|null $fields
     * @param list<class-string> $stack
     *
     * @return array<string, mixed>
     */
    private function classParameter(string $name, string $type, string $class, ?array $fields, ?string $label, array $stack): array
    {
        if ($fields !== null || $this->doctrineMetadata($class) || $this->groupFields($class)) {
            return $this->parameter($name, $type, $label, $this->classFields($class, $fields, [...$stack, $class], 1));
        }
        if (in_array($class, $stack, true)) {
            throw new \InvalidArgumentException($name . ': the data class ' . $class . ' contains itself');
        }

        return $this->parameter($name, $type, $label, $this->dataClassFields($class, [...$stack, $class]));
    }

    /**
     * Item class of a list, read in the PHPDoc by PropertyInfo (@var list<Class>, Class[]…).
     *
     * @param class-string $class
     *
     * @return class-string
     */
    private function itemClass(string $class, string $property, string $where): string
    {
        $item = null;
        if ($this->propertyInfo && method_exists($this->propertyInfo, 'getType')) {
            $type = $this->propertyInfo->getType($class, $property);
            $item = $type ? $this->collectionClass($type) : null;
        } elseif ($this->propertyInfo) {
            foreach ($this->propertyInfo->getTypes($class, $property) ?? [] as $type) {
                foreach ($type->getCollectionValueTypes() as $value) {
                    $item ??= $value->getClassName();
                }
            }
        }

        if ($item === null || !class_exists($item)) {
            throw new \InvalidArgumentException($where . ': unknown item type, to be given in PHPDoc (/** @var list<Class> */)');
        }

        return $item;
    }

    /** Item class of a TypeInfo type (Symfony ≥ 7.1): collection, possibly nullable. */
    private function collectionClass(object $type): ?string
    {
        if (method_exists($type, 'getCollectionValueType')) {
            return $this->className($type->getCollectionValueType());
        }
        if (method_exists($type, 'getTypes')) {
            foreach ($type->getTypes() as $inner) {
                $class = $this->collectionClass($inner);
                if ($class !== null) {
                    return $class;
                }
            }
        }

        return method_exists($type, 'getWrappedType') ? $this->collectionClass($type->getWrappedType()) : null;
    }

    private function className(object $type): ?string
    {
        if (method_exists($type, 'getClassName')) {
            return $type->getClassName();
        }
        if (method_exists($type, 'getTypes')) {
            foreach ($type->getTypes() as $inner) {
                $class = $this->className($inner);
                if ($class !== null) {
                    return $class;
                }
            }
        }

        return method_exists($type, 'getWrappedType') ? $this->className($type->getWrappedType()) : null;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return T|null
     */
    private function attribute(\ReflectionProperty $property, string $attribute): ?object
    {
        $attributes = $property->getAttributes($attribute);

        return $attributes ? $attributes[0]->newInstance() : null;
    }

    /**
     * @param list<array<string, mixed>>|null $children
     *
     * @return array<string, mixed>
     */
    private function parameter(string $name, string $type, ?string $label, ?array $children = null, ?string $format = null): array
    {
        $parameter = ['name' => $name, 'type' => $type];
        if ($label !== null && $label !== '') {
            $parameter['label'] = $label;
        }
        if ($format !== null && $format !== '') {
            $parameter['format'] = $format;
        }
        if ($type === 'object' || $type === 'array') {
            $parameter['children'] = $children ?? [];
        }

        return $parameter;
    }

    /**
     * @param class-string $class
     * @param list<string>|null $fields
     * @param list<class-string> $stack already visited classes
     *
     * @return list<array<string, mixed>>
     */
    private function classFields(string $class, ?array $fields, array $stack, int $depth): array
    {
        $names = $fields ?? $this->groupFields($class);
        $parameters = [];
        foreach ($names as $name) {
            if (!preg_match(self::IDENTIFIER, $name)) {
                continue;
            }
            $parameter = $this->field($class, $name, $stack, $depth);
            if ($parameter !== null) {
                $parameters[] = $parameter;
            }
        }

        return $parameters;
    }

    /**
     * Fields of $class that have one of the Serializer groups.
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    private function groupFields(string $class): array
    {
        $names = [];
        foreach ($this->serializerMetadata->getMetadataFor($class)->getAttributesMetadata() as $attribute) {
            if (array_intersect($attribute->getGroups(), $this->groups)) {
                $names[] = $attribute->getName();
            }
        }

        return $names;
    }

    /**
     * @param class-string $class
     * @param list<class-string> $stack
     *
     * @return array<string, mixed>|null null: field skipped (relation too deep, cycle, unknown type)
     */
    private function field(string $class, string $name, array $stack, int $depth): ?array
    {
        $label = $this->label($name);
        $metadata = $this->doctrineMetadata($class);

        if ($metadata?->hasField($name)) {
            return $this->parameter($name, self::DOCTRINE_TYPES[$metadata->getTypeOfField($name)] ?? 'string', $label);
        }

        if ($metadata?->hasAssociation($name)) {
            $type = $metadata->isCollectionValuedAssociation($name) ? 'array' : 'object';

            return $this->relation($name, $type, $metadata->getAssociationTargetClass($name), $label, $stack, $depth);
        }

        $php = $this->phpType($class, $name);
        if ($php === null) {
            return $this->parameter($name, 'string', $label);
        }
        if ($php->isBuiltin()) {
            $type = ['int' => 'integer', 'float' => 'number', 'bool' => 'boolean', 'string' => 'string'][$php->getName()] ?? null;

            return $type ? $this->parameter($name, $type, $label) : null;
        }

        /** @var class-string $target */
        $target = $php->getName();
        if (is_a($target, \DateTimeInterface::class, true)) {
            return $this->parameter($name, 'datetime', $label);
        }
        if (is_a($target, \Stringable::class, true) && !$this->doctrineMetadata($target)) {
            return $this->parameter($name, 'string', $label);
        }
        if (is_a($target, \Traversable::class, true)) {
            return null; // list without a known item type: skipped
        }

        return $this->relation($name, 'object', $target, $label, $stack, $depth);
    }

    /**
     * @param class-string $target
     * @param list<class-string> $stack
     *
     * @return array<string, mixed>|null
     */
    private function relation(string $name, string $type, string $target, ?string $label, array $stack, int $depth): ?array
    {
        if ($depth >= $this->maxDepth || in_array($target, $stack, true)) {
            return null;
        }
        $children = $this->classFields($target, null, [...$stack, $target], $depth + 1);

        return $children ? $this->parameter($name, $type, $label, $children) : null;
    }

    /**
     * PHP type of the property, otherwise of the getter (get…, is…, has…, or the name itself).
     *
     * @param class-string $class
     */
    private function phpType(string $class, string $name): ?\ReflectionNamedType
    {
        $reflection = new \ReflectionClass($class);
        $type = null;
        if ($reflection->hasProperty($name)) {
            $type = $reflection->getProperty($name)->getType();
        }
        if (!$type instanceof \ReflectionNamedType) {
            foreach (['get', 'is', 'has', ''] as $prefix) {
                $method = $prefix === '' ? $name : $prefix . ucfirst($name);
                if ($reflection->hasMethod($method)) {
                    $type = $reflection->getMethod($method)->getReturnType();
                    break;
                }
            }
        }

        return $type instanceof \ReflectionNamedType ? $type : null;
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>|null
     */
    private function doctrineMetadata(string $class): ?ClassMetadata
    {
        $manager = $this->doctrine?->getManagerForClass($class);

        return $manager?->getClassMetadata($class);
    }

    /** Label: the project's field.<name> translation (Crudit convention), if any. */
    private function label(string $name): ?string
    {
        if (!$this->translator instanceof TranslatorBagInterface) {
            return null;
        }
        $key = 'field.' . strtolower($name);

        return $this->translator->getCatalogue()->has($key) ? $this->translator->trans($key) : null;
    }
}
