<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Find all invoices including soft-deleted ones.
     * Temporarily disables the soft_delete filter for this query.
     *
     * @return Invoice[]
     */
    public function findIncludingDeleted(): array
    {
        $em = $this->getEntityManager();
        $filters = $em->getFilters();

        $wasEnabled = $filters->isEnabled('soft_delete');

        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            $results = $this->createQueryBuilder('i')
                ->getQuery()
                ->getResult();
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }

        return $results;
    }
}
