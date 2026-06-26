<?php

declare(strict_types=1);

namespace App\Service\Client;

use App\Dto\Client\ClientCreateInput;
use App\Dto\Client\ClientFilter;
use App\Dto\Client\ClientUpdateInput;
use App\Entity\Client;
use App\Entity\ClientMonthlyStatus;
use App\Entity\Debt;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\ClientStatus;
use App\Enum\DebtStatus;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\PayMethod;
use App\Repository\ClientRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Config\ConfigService;
use App\Service\Util\PeriodRangeIterator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ClientService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
        private readonly AuditLogger $auditLogger,
        private readonly ConfigService $configService,
    ) {
    }

    public function create(ClientCreateInput $in, User $actor): Client
    {
        if ($this->clientRepository->findOneAliveByInn($in->inn) !== null) {
            throw new ConflictHttpException('INN already exists.');
        }

        $client = new Client();
        $client->setInn($in->inn);
        $client->setName($in->name);
        $client->setPhone($in->phone);
        $client->setPhone2($in->phone2);
        $client->setServiceDate(new \DateTimeImmutable($in->serviceDate));
        $client->setPaymentType(PaymentType::from($in->paymentType));
        $client->setProductCount($in->productCount);
        $client->setStatus(ClientStatus::from($in->status));
        $client->setNotes($in->notes);

        $lastPaidPeriod = trim($in->lastPaidPeriod);
        $this->validateLastPaidPeriod($client->getServiceDate(), $lastPaidPeriod);
        $client->setLastPaidPeriod($lastPaidPeriod);

        $this->em->persist($client);
        $this->em->flush();

        $seededCount = $this->seedPaidHistory($client, $lastPaidPeriod);
        $debt = $this->reconcileOverdueAfterSeeding($client, $lastPaidPeriod, $actor);

        $this->auditLogger->log($actor, 'client.created', 'client', $client->getId(), [
            'inn' => $client->getInn(),
            'name' => $client->getName(),
            'last_paid_period' => $client->getLastPaidPeriod(),
            'history_seeded_months' => $seededCount,
            'debt_months_overdue' => $debt?->getMonthsOverdue(),
            'debt_amount' => $debt?->getAmount(),
        ]);

        return $client;
    }

    public function update(int $id, ClientUpdateInput $in, User $actor): Client
    {
        $client = $this->findOrFail($id);

        if ($client->getInn() !== $in->inn && $this->clientRepository->findOneAliveByInn($in->inn) !== null) {
            throw new ConflictHttpException('INN already exists.');
        }

        $newServiceDate = new \DateTimeImmutable($in->serviceDate);
        $oldServiceDate = $client->getServiceDate();
        $serviceDateChanged = $newServiceDate->format('Y-m-d') !== $oldServiceDate->format('Y-m-d');
        
        $productCountChanged = $client->getProductCount() !== $in->productCount;
        $paymentTypeChanged = $client->getPaymentType()->value !== $in->paymentType;

        $client->setInn($in->inn);
        $client->setName($in->name);
        $client->setPhone($in->phone);
        $client->setPhone2($in->phone2);
        $client->setServiceDate($newServiceDate);
        $client->setPaymentType(PaymentType::from($in->paymentType));
        $client->setProductCount($in->productCount);
        $client->setStatus(ClientStatus::from($in->status));
        $client->setNotes($in->notes);
        $client->setUpdatedAt(new \DateTimeImmutable());

        $oldLastPaid = $client->getLastPaidPeriod();
        $newLastPaid = trim($in->lastPaidPeriod);
        $seededCount = 0;

        $needsReconcile = $newLastPaid !== $oldLastPaid || $serviceDateChanged || $productCountChanged || $paymentTypeChanged;

        if ($needsReconcile) {
            $this->validateLastPaidPeriod($client->getServiceDate(), $newLastPaid);

            // Whether we are pulling history *backward* (newer last_paid is
            // older than the previous one) or shifting service_date forward
            // (which trims the lower bound of the seeded range), our
            // previously seeded history rows may now sit outside the valid
            // window. Backward/shift edits therefore require us to wipe the
            // stale slate so reconcileOverdueAfterSeeding can rebuild it.
            $isBackward = $oldLastPaid !== null && strcmp($newLastPaid, $oldLastPaid) < 0;
            $serviceMovedForward = $serviceDateChanged
                && $oldServiceDate->format('Y-m') < $newServiceDate->format('Y-m');

            if ($isBackward || $serviceMovedForward) {
                $this->clearSeededHistoryOutsideRange(
                    $client,
                    $client->getServiceDate()->format('Y-m'),
                    $newLastPaid,
                );
            }

            $client->setLastPaidPeriod($newLastPaid);
            $seededCount = $this->seedPaidHistory($client, $newLastPaid);
            $this->reconcileOverdueAfterSeeding($client, $newLastPaid, $actor);
        }

        $this->em->flush();

        $this->auditLogger->log($actor, 'client.updated', 'client', $client->getId(), [
            'inn' => $client->getInn(),
            'name' => $client->getName(),
            'last_paid_period' => $client->getLastPaidPeriod(),
            'history_seeded_months' => $seededCount,
        ]);

        return $client;
    }

    public function softDelete(int $id, User $actor): void
    {
        $client = $this->findOrFail($id);

        // Close any active debt before soft-deleting the client so that the
        // /api/debtors listing (which inner-joins clients) does not display a
        // dangling reference and so that admins still see the audit trail.
        $activeDebt = $this->em->getRepository(Debt::class)->findOneBy([
            'client' => $client,
            'status' => DebtStatus::Active,
        ]);
        if ($activeDebt !== null) {
            $this->closeDebt($activeDebt, $actor);
        }

        // Block hard delete if client has invoice items or active debts
        $hasRelations = (bool) $this->em->getConnection()->fetchOne(
            'SELECT 1 FROM invoice_items WHERE client_name_snapshot IS NOT NULL AND client_inn_snapshot = ? LIMIT 1',
            [$client->getInn()]
        );

        // Soft delete is always allowed; the check above is informational for future hard-delete
        $client->softDelete();
        $this->em->flush();

        $this->auditLogger->log($actor, 'client.deleted', 'client', $client->getId(), [
            'inn' => $client->getInn(),
        ]);
    }

    public function findById(int $id): Client
    {
        return $this->findOrFail($id);
    }

    /**
     * Excel import paytida chaqiriladi: to'lov tarixini seed qiladi va
     * qarzdorlikni qo'lda qo'shishdagi kabi hisoblaydi.
     */
    public function seedAndReconcileForImport(Client $client, string $lastPaidPeriod, User $actor): void
    {
        if ($lastPaidPeriod !== '') {
            $this->seedPaidHistory($client, $lastPaidPeriod);
            $this->reconcileOverdueAfterSeeding($client, $lastPaidPeriod, $actor);
        } else {
            // last_paid_period yo'q bo'lsa: joriy oydan oldingi barcha oylar qarzdor
            $lastPaidPeriod = '';
            $this->reconcileOverdueAfterSeeding($client, $lastPaidPeriod, $actor);
        }
    }

    /**
     * Return the subset of $clientIds that currently have an active debt.
     *
     * @param int[] $clientIds
     * @return int[]
     */
    public function findIdsWithActiveDebt(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT client_id FROM debts WHERE status = :status AND client_id IN (:ids)',
            ['status' => 'active', 'ids' => $clientIds],
            ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        );

        return array_map(static fn ($row) => (int) $row['client_id'], $rows);
    }

    /**
     * @return array{items: Client[], total: int}
     */
    public function findPaginated(ClientFilter $f): array
    {
        $qb = $this->clientRepository->createQueryBuilder('c');

        if ($f->search !== null && $f->search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.inn LIKE :search OR c.phone LIKE :search')
                ->setParameter('search', '%' . $f->search . '%');
        }

        if ($f->paymentType !== null && $f->paymentType !== '') {
            $qb->andWhere('c.paymentType = :pt')
                ->setParameter('pt', $f->paymentType);
        }

        if ($f->status !== null && $f->status !== '') {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $f->status);
        }

        // Sort
        [$field, $dir] = match ($f->sort) {
            'name_asc' => ['c.name', 'ASC'],
            'name_desc' => ['c.name', 'DESC'],
            'id_asc' => ['c.id', 'ASC'],
            'created_asc' => ['c.createdAt', 'ASC'],
            'created_desc' => ['c.createdAt', 'DESC'],
            default => ['c.id', 'DESC'],
        };
        $qb->orderBy($field, $dir);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $qb->setFirstResult(($f->page - 1) * $f->pageSize)
            ->setMaxResults($f->pageSize);

        return ['items' => $qb->getQuery()->getResult(), 'total' => $total];
    }

    private function findOrFail(int $id): Client
    {
        $client = $this->clientRepository->find($id);
        if ($client === null) {
            throw new NotFoundHttpException('Client not found.');
        }
        return $client;
    }

    /**
     * Ensure that last_paid_period is not earlier than the client's service month
     * and not in the future.
     *
     * The format itself (YYYY-MM) is enforced by PeriodConstraint on the DTO.
     */
    private function validateLastPaidPeriod(\DateTimeImmutable $serviceDate, string $lastPaidPeriod): void
    {
        $servicePeriod = $serviceDate->format('Y-m');
        if (strcmp($lastPaidPeriod, $servicePeriod) < 0) {
            throw new UnprocessableEntityHttpException(sprintf(
                "last_paid_period (%s) xizmat sanasi oyidan (%s) oldin bo'lishi mumkin emas",
                $lastPaidPeriod,
                $servicePeriod
            ));
        }

        $currentPeriod = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))->format('Y-m');
        if (strcmp($lastPaidPeriod, $currentPeriod) > 0) {
            throw new UnprocessableEntityHttpException(sprintf(
                "last_paid_period (%s) joriy oydan (%s) keyin bo'lishi mumkin emas",
                $lastPaidPeriod,
                $currentPeriod
            ));
        }
    }

    /**
     * Insert ClientMonthlyStatus rows from the client's service_date month
     * through $lastPaidPeriod (inclusive), marking each as paid via naqt.
     *
     * Idempotent: existing rows are preserved untouched. Returns the number
     * of new rows inserted.
     */
    private function seedPaidHistory(Client $client, string $lastPaidPeriod): int
    {
        $startPeriod = $client->getServiceDate()->format('Y-m');
        $now = new \DateTimeImmutable();
        $cmsRepo = $this->em->getRepository(ClientMonthlyStatus::class);

        $inserted = 0;
        $batch = 0;

        foreach (PeriodRangeIterator::between($startPeriod, $lastPaidPeriod) as $period) {
            $existing = $cmsRepo->findOneBy(['client' => $client, 'period' => $period]);

            if ($existing !== null) {
                // Promote unpaid → paid (e.g. months previously flagged by the
                // initial reconcile or by the daily debt cron). Never touch
                // skipped rows or rows linked to an active debt that is still
                // outstanding — those will be handled by reconcileOverdueAfterSeeding.
                if ($existing->getPaymentStatus() === PaymentStatus::Unpaid) {
                    $existing->setPaymentStatus(PaymentStatus::Paid);
                    $existing->setPaymentMethod(PayMethod::Naqt);
                    $existing->setPaymentTypeSnapshot($client->getPaymentType());
                    $existing->setPaidAt($now);
                    $existing->setNotes('client_creation_history');
                    $existing->setDebt(null);
                    $inserted++;
                }
                continue;
            }

            $cms = new ClientMonthlyStatus();
            $cms->setClient($client);
            $cms->setPeriod($period);
            $cms->setPaymentStatus(PaymentStatus::Paid);
            $cms->setPaymentMethod(PayMethod::Naqt);
            $cms->setPaymentTypeSnapshot($client->getPaymentType());
            $cms->setPaidAt($now);
            $cms->setNotes('client_creation_history');

            $this->em->persist($cms);
            $inserted++;

            // Flush every 50 rows to keep the unit of work bounded for very old clients.
            if (++$batch >= 50) {
                $this->em->flush();
                $batch = 0;
            }
        }

        if ($batch > 0) {
            $this->em->flush();
        }

        return $inserted;
    }

    /**
     * After paid-history seeding, mark any remaining unpaid months as overdue
     * and create (or update) an active Debt for the client. If
     * last_paid_period now covers the current overdue boundary, any existing
     * active debt is closed (status=paid, paid_method=naqt).
     */
    private function reconcileOverdueAfterSeeding(Client $client, string $lastPaidPeriod, User $actor): ?Debt
    {
        $today = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
        $lastOverdueCandidate = $this->computeLastOverduePeriod($today, $client->getServiceDate());

        $debtRepo = $this->em->getRepository(Debt::class);
        $existingDebt = $debtRepo->findOneBy([
            'client' => $client,
            'status' => DebtStatus::Active,
        ]);

        // Client is paid up through the boundary: nothing to flag, but a
        // lingering active debt must be closed (forward edit scenario).
        if (strcmp($lastPaidPeriod, $lastOverdueCandidate) >= 0) {
            if ($existingDebt !== null) {
                $this->closeDebt($existingDebt, $actor);
            }
            return null;
        }

        $firstOverdue = $this->nextPeriodAfter($lastPaidPeriod);
        $cmsRepo = $this->em->getRepository(ClientMonthlyStatus::class);
        $now = new \DateTimeImmutable();

        $monthsOverdue = 0;
        foreach (PeriodRangeIterator::between($firstOverdue, $lastOverdueCandidate) as $period) {
            $cms = $cmsRepo->findOneBy(['client' => $client, 'period' => $period]);
            if ($cms === null) {
                $cms = new ClientMonthlyStatus();
                $cms->setClient($client);
                $cms->setPeriod($period);
                $cms->setPaymentStatus(PaymentStatus::Unpaid);
                $cms->setPaymentTypeSnapshot($client->getPaymentType());
                $this->em->persist($cms);
                $monthsOverdue++;
                continue;
            }

            // Existing row: never overwrite a paid month, but flip skipped -> unpaid.
            if ($cms->getPaymentStatus() === PaymentStatus::Paid) {
                continue;
            }
            $cms->setPaymentStatus(PaymentStatus::Unpaid);
            $monthsOverdue++;
        }

        if ($monthsOverdue === 0) {
            $this->em->flush();
            if ($existingDebt !== null) {
                $this->closeDebt($existingDebt, $actor);
            }
            return null;
        }

        $unitPrice = $this->configService->get('unit_price');
        $monthlyAmount = bcmul($unitPrice, (string) $client->getProductCount(), 2);
        $totalAmount = bcmul($monthlyAmount, (string) $monthsOverdue, 2);

        if ($existingDebt !== null) {
            $existingDebt->setMonthlyAmount($monthlyAmount);
            $existingDebt->setMonthsOverdue($monthsOverdue);
            $existingDebt->setAmount($totalAmount);
            $existingDebt->setFirstOverduePeriod($firstOverdue);
            $existingDebt->setLastOverduePeriod($lastOverdueCandidate);
            $existingDebt->setPaymentTypeSnapshot($client->getPaymentType());
            $existingDebt->setDueDate($today);
            $existingDebt->setUpdatedAt($now);
            $debt = $existingDebt;
        } else {
            $debt = new Debt();
            $debt->setClient($client);
            $debt->setMonthlyAmount($monthlyAmount);
            $debt->setMonthsOverdue($monthsOverdue);
            $debt->setAmount($totalAmount);
            $debt->setFirstOverduePeriod($firstOverdue);
            $debt->setLastOverduePeriod($lastOverdueCandidate);
            $debt->setPaymentTypeSnapshot($client->getPaymentType());
            $debt->setDueDate($today);
            $this->em->persist($debt);
        }

        $this->em->flush();

        // Link the unpaid CMS rows to the debt for traceability.
        foreach (PeriodRangeIterator::between($firstOverdue, $lastOverdueCandidate) as $period) {
            $cms = $cmsRepo->findOneBy(['client' => $client, 'period' => $period]);
            if ($cms !== null && $cms->getPaymentStatus() === PaymentStatus::Unpaid && $cms->getDebt() === null) {
                $cms->setDebt($debt);
            }
        }
        $this->em->flush();

        return $debt;
    }

    /**
     * Close an active debt because last_paid_period now covers it. We mark
     * the debt as paid via naqt (operator manual reconciliation), record a
     * Payment row for audit, and free the unique active-debt slot.
     */
    private function closeDebt(Debt $debt, User $actor): void
    {
        $now = new \DateTimeImmutable();

        $payment = new Payment();
        $payment->setClient($debt->getClient());
        $payment->setDebt($debt);
        $payment->setAmount($debt->getAmount());
        $payment->setPaymentMethod(PayMethod::Naqt);
        $payment->setPeriod($debt->getLastOverduePeriod());
        $payment->setCreatedBy($actor);
        $payment->setNotes('client_last_paid_period_update');
        $this->em->persist($payment);

        $debt->setStatus(DebtStatus::Paid);
        $debt->setPaidAt($now);
        $debt->setPaidMethod(PayMethod::Naqt);
        $debt->setPaidBy($actor);
        $debt->setUpdatedAt($now);

        $this->em->flush();

        $this->auditLogger->log($actor, 'debt.paid_by_history_update', 'debt', $debt->getId(), [
            'client_id' => $debt->getClient()->getId(),
            'amount' => $debt->getAmount(),
            'method' => PayMethod::Naqt->value,
        ]);
    }

    /**
     * Determine the most recent period that must already be paid.
     *
     * Business rule (operator-driven): a client is considered overdue as
     * soon as the current month differs from their last_paid_period.
     * The monthly anniversary day inside the current month is intentionally
     * ignored — operators want immediate visibility of any month gap.
     */
    private function computeLastOverduePeriod(\DateTimeImmutable $today, \DateTimeImmutable $serviceDate): string
    {
        return $today->format('Y-m');
    }

    private function nextPeriodAfter(string $period): string
    {
        return (\DateTimeImmutable::createFromFormat('Y-m-d', $period . '-01'))
            ->modify('+1 month')
            ->format('Y-m');
    }

    /**
     * Remove only the CMS rows that we previously inserted as part of the
     * paid-history seeding (notes='client_creation_history') for periods
     * outside [$startPeriod, $endPeriod]. Rows produced by other sources
     * (mark-monthly-paid, debt payments, invoice generation, daily debt
     * cron) are intentionally preserved so that backward edits do not
     * destroy independent payment records.
     *
     * Also drops any active debt — reconcileOverdueAfterSeeding will
     * re-create it with the correct months_overdue afterwards.
     */
    private function clearSeededHistoryOutsideRange(
        Client $client,
        string $startPeriod,
        string $endPeriod,
    ): void {
        $conn = $this->em->getConnection();
        $conn->executeStatement(
            "DELETE FROM client_monthly_status
             WHERE client_id = :client_id
               AND notes = 'client_creation_history'
               AND (period < :start OR period > :end)",
            [
                'client_id' => $client->getId(),
                'start' => $startPeriod,
                'end' => $endPeriod,
            ],
        );

        // Drop the unpaid CMS rows we previously linked to the soon-to-be
        // recreated debt; they will be re-inserted by reconcileOverdueAfterSeeding.
        $conn->executeStatement(
            "DELETE cms FROM client_monthly_status cms
             INNER JOIN debts d ON cms.debt_id = d.id
             WHERE cms.client_id = :client_id
               AND d.status = 'active'",
            ['client_id' => $client->getId()],
        );

        $debt = $this->em->getRepository(Debt::class)->findOneBy([
            'client' => $client,
            'status' => DebtStatus::Active,
        ]);
        if ($debt !== null) {
            $this->em->remove($debt);
            $this->em->flush();
        }
    }
}
