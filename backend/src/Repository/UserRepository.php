<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Find all users including soft-deleted ones.
     * Temporarily disables the soft_delete filter for this query.
     *
     * @return User[]
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
            $results = $this->createQueryBuilder('u')
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
