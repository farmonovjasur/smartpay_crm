<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use App\Enum\ClientStatus;
use App\Enum\PaymentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function findOneAliveByInn(string $inn): ?Client
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.inn = :inn')
            ->setParameter('inn', $inn)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Active fakt clients without an invoice for the given period.
     * @return Client[]
     */
    public function findFaktClientsWithoutInvoiceForPeriod(string $period): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT c.id FROM clients c
            WHERE c.payment_type = 'fakt'
              AND c.status = 'faol'
              AND c.deleted_at IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM client_monthly_status cms
                WHERE cms.client_id = c.id AND cms.period = :period AND cms.payment_status = 'paid'
              )
        SQL;

        $ids = $conn->fetchFirstColumn($sql, ['period' => $period]);

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Active clients whose service_date day matches today (with effectiveDay logic).
     * @return Client[]
     */
    public function findActiveClientsWithAnniversaryDay(\DateTimeImmutable $today): array
    {
        $day = (int) $today->format('d');
        $daysInMonth = (int) $today->format('t');
        $isLastDay = ($day === $daysInMonth);

        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', ClientStatus::Faol);

        if ($isLastDay) {
            // Match clients whose service_date day >= daysInMonth (e.g. 29,30,31 on Feb 28)
            $qb->andWhere('DAY(c.serviceDate) >= :day')
                ->setParameter('day', $daysInMonth);
        } else {
            $qb->andWhere('DAY(c.serviceDate) = :day')
                ->setParameter('day', $day);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Client[]
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
            return $this->createQueryBuilder('c')->getQuery()->getResult();
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }
}
