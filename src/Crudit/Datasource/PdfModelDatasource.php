<?php

namespace Lle\PdfGeneratorBundle\Crudit\Datasource;

use Doctrine\ORM\EntityManagerInterface;
use Lle\CruditBundle\Datasource\AbstractDoctrineDatasource;
use Lle\CruditBundle\Filter\FilterState;
use Lle\PdfGeneratorBundle\Crudit\Datasource\Filterset\PdfModelFilterSet;
use Lle\PdfGeneratorBundle\Entity\PdfModelInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\Attribute\Required;

/** PDF templates, of the configured class (lle_pdf_generator.class). */
class PdfModelDatasource extends AbstractDoctrineDatasource
{
    /**
     * @param class-string<PdfModelInterface> $modelClass
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        FilterState $filterState,
        #[Autowire(param: 'lle.pdf.class')]
        protected string $modelClass,
    ) {
        parent::__construct($entityManager, $filterState);
    }

    public function getClassName(): string
    {
        return $this->modelClass;
    }

    #[Required]
    public function setFilterset(PdfModelFilterSet $filterSet): void
    {
        $this->filterset = $filterSet;
    }

    public function newInstance(): object
    {
        $model = new $this->modelClass();
        $model->setPath(null);

        return $model;
    }
}
