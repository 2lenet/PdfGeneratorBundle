<?php

namespace Lle\PdfGeneratorBundle\DataModel;

/**
 * Data source of a crudit_report template. The data is an object of a dedicated class (getDataClass()): its typed
 * public properties describe the template parameters (a single source of truth: the code that builds the data).
 * A template with a data source (PdfModel::datasource) gets these parameters read-only in the designer, always up
 * to date, and its data is extracted according to them at render time.
 *
 * The services implementing this interface are registered automatically (autoconfigure).
 */
interface CruditDataModelInterface
{
    public const TAG = 'lle.pdf.crudit_data_model';

    /** Name of the data source, stored in PdfModel::datasource. */
    public static function getName(): string;

    /** Label shown in the templates screen. */
    public function getLabel(): string;

    /**
     * Class of the data sent to the template: typed public properties (values, entities, data classes, lists typed
     * in PHPDoc as list<Class>), refined if needed by the DataModel\Attribute attributes.
     *
     * @return class-string
     */
    public function getDataClass(): string;

    /**
     * Sample data for the designer preview and "Show the PDF": a getDataClass() object, built by the same code as
     * the real generation (e.g. the data of the last order); null: no sample.
     */
    public function getSample(): ?object;
}
