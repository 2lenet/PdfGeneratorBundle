<?php

namespace Lle\PdfGeneratorBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Lle\PdfGeneratorBundle\Entity\PdfModelVersion;

/**
 * @extends EntityRepository<PdfModelVersion>
 */
class PdfModelVersionRepository extends EntityRepository
{
    /** Number of the next version of a template: 1 for its first file. */
    public function nextNumber(int $modelId): int
    {
        $max = $this->createQueryBuilder('version')
            ->select('MAX(version.number)')
            ->where('version.modelId = :modelId')
            ->setParameter('modelId', $modelId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$max + 1;
    }

    public function hasVersions(int $modelId): bool
    {
        return $this->count(['modelId' => $modelId]) > 0;
    }

    /**
     * Versions of a template, the last one first.
     *
     * @return list<PdfModelVersion>
     */
    public function findByModel(int $modelId): array
    {
        return $this->findBy(['modelId' => $modelId], ['number' => 'DESC']);
    }
}
