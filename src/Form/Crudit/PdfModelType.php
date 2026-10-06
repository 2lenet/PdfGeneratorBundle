<?php

namespace Lle\PdfGeneratorBundle\Form\Crudit;

use Lle\CruditBundle\Form\Type\FileType;
use Lle\PdfGeneratorBundle\Crudit\Config\PdfModelCrudConfig;
use Lle\PdfGeneratorBundle\DataModel\DataModelRegistry;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Lle\PdfGeneratorBundle\Generator\CruditReportGenerator;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Form of the PDF templates admin screen. */
class PdfModelType extends AbstractType
{
    public function __construct(
        private PdfGenerator $pdfGenerator,
        private CruditReportGenerator $cruditReportGenerator,
        private DataModelRegistry $dataModels,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $types = $this->pdfGenerator->getTypes();

        $builder->add('libelle', null, ['label' => 'field.libelle']);
        $builder->add('code', null, ['label' => 'field.code']);
        // empty: default type (lle_pdf_generator.default_generator), the existing templates do not change
        $builder->add('type', ChoiceType::class, [
            'label' => 'field.type',
            'choices' => array_combine($types, $types),
            'choice_translation_domain' => false,
            'required' => false,
            'placeholder' => $this->pdfGenerator->getDefaultGenerator(),
        ]);
        // crudit_report: data described by the PHP (read-only parameters in the designer, always up to date),
        // or empty: structure specific to the template, defined in the designer
        if (in_array(CruditReportGenerator::getName(), $types, true)) {
            $builder->add('datasource', ChoiceType::class, [
                'label' => 'field.datasource',
                'help' => 'help.datasource',
                'choices' => $this->dataModels->getChoices(),
                'choice_translation_domain' => false,
                'required' => false,
                'placeholder' => 'placeholder.datasource',
            ]);
        }
        $builder->add('file', FileType::class, ['label' => 'field.file']);
        $builder->add('description', null, ['label' => 'field.description']);

        // A crudit_report template can be created without file: it starts from an empty template, to edit in the designer.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $model = $event->getData();

            if (
                $model instanceof PdfModelInterface
                && $model->getType() === CruditReportGenerator::getName()
                && !$model->getFile()
                && !$model->getPath()
                && $event->getForm()->isValid()
            ) {
                $model->setPath($this->cruditReportGenerator->createTemplate(
                    $this->pdfGenerator->getPath(),
                    (string)$model->getLibelle(),
                ));
                $model->setUpdatedAt(new \DateTime());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => PdfModelCrudConfig::TRANSLATION_DOMAIN]);
    }
}
