<?php

namespace Lle\PdfGeneratorBundle\Crudit\Datasource\Filterset;

use Lle\CruditBundle\Datasource\AbstractFilterSet;
use Lle\CruditBundle\Filter\FilterType\StringFilterType;

class PdfModelFilterSet extends AbstractFilterSet
{
    /** @return StringFilterType[] */
    public function getFilters(): array
    {
        return [
            StringFilterType::new('libelle'),
            StringFilterType::new('code'),
        ];
    }
}
