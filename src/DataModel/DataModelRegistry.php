<?php

namespace Lle\PdfGeneratorBundle\DataModel;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Data sources of the crudit_report templates (CruditDataModelInterface services), by name. */
class DataModelRegistry
{
    /** @var array<string, CruditDataModelInterface>|null instantiated on first use */
    private ?array $models = null;

    /** @var array<string, list<array<string, mixed>>> by data source and locale (translated labels) */
    private array $parameters = [];

    /**
     * @param iterable<CruditDataModelInterface> $sources iterated on first use only: a data source may depend on
     *        PdfGenerator (BlGenerator…), which depends on this registry
     */
    public function __construct(
        protected iterable $sources,
        protected ClassMetadataFactoryInterface $serializerMetadata,
        protected DataExtractor $extractor,
        protected ?ManagerRegistry $doctrine = null,
        protected ?TranslatorInterface $translator = null,
        protected ?PropertyInfoExtractorInterface $propertyInfo = null,
    ) {
    }

    public function has(string $name): bool
    {
        return isset($this->models()[$name]);
    }

    public function get(string $name): CruditDataModelInterface
    {
        return $this->models()[$name] ?? throw new \InvalidArgumentException(
            'Unknown data source ' . $name . ' (' . (implode(', ', array_keys($this->models())) ?: 'no data source declared') . ')'
        );
    }

    /**
     * Choices of the templates screen: label → name.
     *
     * @return array<string, string>
     */
    public function getChoices(): array
    {
        $choices = [];
        foreach ($this->models() as $name => $model) {
            $choices[$model->getLabel() . ' (' . $name . ')'] = $name;
        }
        ksort($choices);

        return $choices;
    }

    /**
     * Parameters described by the data class of the data source.
     *
     * @return list<array<string, mixed>>
     */
    public function getParameters(string $name): array
    {
        $key = $name . '@' . $this->translator?->getLocale();
        if (!isset($this->parameters[$key])) {
            $builder = new DataModelBuilder($this->serializerMetadata, $this->doctrine, $this->translator, $this->propertyInfo);
            $this->parameters[$key] = $builder->describe($this->get($name)->getDataClass());
        }

        return $this->parameters[$key];
    }

    /** Sample data of the data source, extracted according to its parameters; null: no sample. */
    public function getSample(string $name): ?\stdClass
    {
        $sample = $this->get($name)->getSample();

        return $sample === null ? null : $this->extractor->extract($this->getParameters($name), $sample);
    }

    /** @return array<string, CruditDataModelInterface> */
    private function models(): array
    {
        if ($this->models === null) {
            $this->models = [];
            foreach ($this->sources as $model) {
                $this->models[$model::getName()] = $model;
            }
        }

        return $this->models;
    }

    /** Data sent to the template, extracted according to the parameters of the data source. */
    public function extract(string $name, mixed $data): \stdClass
    {
        return $this->extractor->extract($this->getParameters($name), $data);
    }
}
