<?php

declare(strict_types=1);

namespace App\Service\Ledger;

use App\Entity\Client;
use App\Entity\LedgerEntry;
use App\Entity\LedgerItem;
use App\Entity\LedgerPayment;
use App\Entity\User;
use App\Enum\LedgerStatus;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Qarzdaftari biznes logikasi.
 *
 * Qarzga xizmat berish, to'lash (to'liq/qisman), tahrirlash va o'chirish operatsiyalari.
 * To'lov usuli (naqt/fakt) talab qilinmaydi — faqat summa kiritiladi.
 */
final class LedgerService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Yangi qarz yozuvi yaratish.
     *
     * @param int          $clientId  Mijoz ID
     * @param array<array{description: string, amount: string}> $items Xizmat qatorlari
     * @param string|null  $notes     Umumiy izoh
     * @param User         $actor     Yaratuvchi foydalanuvchi
     */
    public function create(int $clientId, array $items, ?string $notes, User $actor): LedgerEntry
    {
        $client = $this->em->find(Client::class, $clientId);
        if (!$client || $client->isDeleted()) {
            throw new NotFoundHttpException('Mijoz topilmadi.');
        }

        if (empty($items)) {
            throw new UnprocessableEntityHttpException('Kamida bitta xizmat qatori kiritilishi shart.');
        }

        $entry = new LedgerEntry();
        $entry->setClient($client);
        $entry->setNotes($notes);
        $entry->setCreatedBy($actor);

        foreach ($items as $itemData) {
            $this->validateItemData($itemData);

            $item = new LedgerItem();
            $item->setDescription(trim($itemData['description']));
            $item->setAmount($itemData['amount']);
            $entry->addItem($item);
        }

        $entry->recalculateTotal();

        $this->em->persist($entry);
        $this->em->flush();

        $this->auditLogger->log(
            $actor,
            'ledger.created',
            'ledger_entry',
            $entry->getId(),
            [
                'client_id' => $clientId,
                'total_amount' => $entry->getTotalAmount(),
                'items_count' => count($items),
            ]
        );

        return $entry;
    }

    /**
     * Qarz yozuvini tahrirlash (faqat xato tuzatish uchun).
     * Faqat 'active' yoki 'partial' holatda ruxsat beriladi.
     */
    public function update(int $entryId, array $items, ?string $notes, User $actor): LedgerEntry
    {
        $entry = $this->findOrFail($entryId);

        if ($entry->getStatus() === LedgerStatus::Paid) {
            throw new UnprocessableEntityHttpException("To'langan qarzni tahrirlash mumkin emas.");
        }

        if (empty($items)) {
            throw new UnprocessableEntityHttpException('Kamida bitta xizmat qatori kiritilishi shart.');
        }

        // Eski items o'chiriladi, yangilari yaratiladi
        $entry->clearItems();

        foreach ($items as $itemData) {
            $this->validateItemData($itemData);

            $item = new LedgerItem();
            $item->setDescription(trim($itemData['description']));
            $item->setAmount($itemData['amount']);
            $entry->addItem($item);
        }

        $entry->setNotes($notes);
        $entry->recalculateTotal();
        $entry->setUpdatedAt(new \DateTimeImmutable());

        // Agar qisman to'langan bo'lsa va yangi total paidAmount dan kam bo'lsa — tekshirish
        if (bccomp($entry->getTotalAmount(), $entry->getPaidAmount(), 2) <= 0) {
            $entry->setStatus(LedgerStatus::Paid);
            $entry->setPaidAt(new \DateTimeImmutable());
        }

        $this->em->flush();

        $this->auditLogger->log(
            $actor,
            'ledger.updated',
            'ledger_entry',
            $entry->getId(),
            [
                'total_amount' => $entry->getTotalAmount(),
                'items_count' => count($items),
            ]
        );

        return $entry;
    }

    /**
     * Qarzni to'lash (to'liq yoki qisman).
     * To'lov usuli talab qilinmaydi.
     *
     * @return array{entry: LedgerEntry, paid: string, remaining: string, fully_paid: bool}
     */
    public function pay(int $entryId, string $amount, User $actor): array
    {
        $entry = $this->findOrFail($entryId);

        if ($entry->getStatus() === LedgerStatus::Paid) {
            throw new UnprocessableEntityHttpException('Bu qarz allaqachon to\'liq to\'langan.');
        }

        if (!is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
            throw new UnprocessableEntityHttpException("To'lov summasi musbat son bo'lishi kerak.");
        }

        $remaining = $entry->getRemainingAmount();

        // To'lov summasi qolgan qarzdan katta bo'lmasligi kerak
        if (bccomp($amount, $remaining, 2) > 0) {
            $amount = $remaining; // ortiqcha qabul qilinmaydi
        }

        $entry->addPaidAmount($amount);
        $entry->setUpdatedAt(new \DateTimeImmutable());

        $newRemaining = $entry->getRemainingAmount();
        $fullyPaid = bccomp($newRemaining, '0', 2) <= 0;

        if ($fullyPaid) {
            $entry->setStatus(LedgerStatus::Paid);
            $entry->setPaidAt(new \DateTimeImmutable());
            $entry->setPaidBy($actor);
        } else {
            $entry->setStatus(LedgerStatus::Partial);
            $entry->setPaidBy($actor);
        }

        $payment = new LedgerPayment($entry, (string) $amount);
        $payment->setCreatedBy($actor);
        $this->em->persist($payment);

        $this->em->flush();

        $this->auditLogger->log(
            $actor,
            'ledger.paid',
            'ledger_entry',
            $entry->getId(),
            [
                'amount' => $amount,
                'remaining' => $newRemaining,
                'fully_paid' => $fullyPaid,
            ]
        );

        return [
            'entry' => $entry,
            'paid' => $amount,
            'remaining' => $newRemaining,
            'fully_paid' => $fullyPaid,
            'payment' => [
                'id' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'created_at' => $payment->getCreatedAt()->format('c'),
                'created_by' => $payment->getCreatedBy()?->getName(),
            ],
        ];
    }

    /**
     * Qarz yozuvini o'chirish (faqat active holatda).
     */
    public function delete(int $entryId, User $actor): void
    {
        $entry = $this->findOrFail($entryId);

        if ($entry->getStatus() !== LedgerStatus::Active) {
            throw new UnprocessableEntityHttpException("Faqat faol qarzlarni o'chirish mumkin.");
        }

        $clientId = $entry->getClient()->getId();
        $totalAmount = $entry->getTotalAmount();

        $this->em->remove($entry);
        $this->em->flush();

        $this->auditLogger->log(
            $actor,
            'ledger.deleted',
            'ledger_entry',
            $entryId,
            [
                'client_id' => $clientId,
                'total_amount' => $totalAmount,
            ]
        );
    }

    /**
     * Paginated ro'yxat (qidiruv va status filter bilan).
     *
     * @return array{data: array, total: int, page: int, pageSize: int}
     */
    public function findPaginated(int $page, int $pageSize, string $status = 'all', string $search = ''): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e', 'c')
            ->from(LedgerEntry::class, 'e')
            ->innerJoin('e.client', 'c')
            ->orderBy('e.id', 'DESC');

        if ($status !== 'all') {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.inn LIKE :search OR c.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var LedgerEntry[] $entries */
        $entries = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (LedgerEntry $e) => [
            'id' => $e->getId(),
            'client_id' => $e->getClient()->getId(),
            'client_name' => $e->getClient()->getName(),
            'client_inn' => $e->getClient()->getInn(),
            'client_phone' => $e->getClient()->getPhone(),
            'total_amount' => $e->getTotalAmount(),
            'paid_amount' => $e->getPaidAmount(),
            'remaining_amount' => $e->getRemainingAmount(),
            'status' => $e->getStatus()->value,
            'items_count' => $e->getItems()->count(),
            'notes' => $e->getNotes(),
            'created_at' => $e->getCreatedAt()->format('c'),
            'updated_at' => $e->getUpdatedAt()?->format('c'),
            'paid_at' => $e->getPaidAt()?->format('c'),
            'created_by_name' => $e->getCreatedBy()?->getName() ?? 'Tizim',
        ], $entries);

        return ['data' => $data, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    /**
     * Bitta yozuv batafsil (items bilan).
     */
    public function getDetail(int $entryId): array
    {
        $entry = $this->findOrFail($entryId);
        $client = $entry->getClient();

        $items = array_map(fn (LedgerItem $i) => [
            'id' => $i->getId(),
            'description' => $i->getDescription(),
            'amount' => $i->getAmount(),
            'created_at' => $i->getCreatedAt()->format('c'),
        ], $entry->getItems()->toArray());

        $payments = array_map(fn ($p) => [
            'id' => $p->getId(),
            'amount' => $p->getAmount(),
            'created_at' => $p->getCreatedAt()->format('c'),
            'created_by' => $p->getCreatedBy()?->getName(),
        ], $entry->getPayments()->toArray());

        return [
            'id' => $entry->getId(),
            'client_id' => $client->getId(),
            'client_name' => $client->getName(),
            'client_inn' => $client->getInn(),
            'client_phone' => $client->getPhone(),
            'client_payment_type' => $client->getPaymentType()->value,
            'total_amount' => $entry->getTotalAmount(),
            'paid_amount' => $entry->getPaidAmount(),
            'remaining_amount' => $entry->getRemainingAmount(),
            'status' => $entry->getStatus()->value,
            'notes' => $entry->getNotes(),
            'items' => $items,
            'items_count' => count($items),
            'payments' => $payments,
            'created_at' => $entry->getCreatedAt()->format('c'),
            'updated_at' => $entry->getUpdatedAt()?->format('c'),
            'paid_at' => $entry->getPaidAt()?->format('c'),
            'paid_by_name' => $entry->getPaidBy()?->getName(),
            'created_by_name' => $entry->getCreatedBy()?->getName() ?? 'Tizim',
        ];
    }

    /**
     * Dashboard uchun umumiy statistika.
     */
    public function getSummary(): array
    {
        $conn = $this->em->getConnection();

        $row = $conn->fetchAssociative(<<<'SQL'
            SELECT
                COALESCE(SUM(total_amount), '0.00')                                      AS total_amount,
                COALESCE(SUM(paid_amount), '0.00')                                       AS total_paid,
                COALESCE(SUM(total_amount - paid_amount), '0.00')                        AS total_remaining,
                COALESCE(SUM(status = 'active'), 0)                                      AS active_count,
                COALESCE(SUM(status = 'partial'), 0)                                     AS partial_count,
                COALESCE(SUM(status = 'paid'), 0)                                        AS paid_count
            FROM ledger_entries
        SQL);

        return [
            'total_amount' => $row['total_amount'],
            'total_paid' => $row['total_paid'],
            'total_remaining' => $row['total_remaining'],
            'active_count' => (int) $row['active_count'],
            'partial_count' => (int) $row['partial_count'],
            'paid_count' => (int) $row['paid_count'],
        ];
    }

    // ─── Private helpers ───────────────────────────────────────────────

    private function findOrFail(int $entryId): LedgerEntry
    {
        $entry = $this->em->find(LedgerEntry::class, $entryId);
        if ($entry === null) {
            throw new NotFoundHttpException('Qarz yozuvi topilmadi.');
        }
        return $entry;
    }

    private function validateItemData(array $itemData): void
    {
        $desc = trim($itemData['description'] ?? '');
        $amount = $itemData['amount'] ?? '';

        if ($desc === '') {
            throw new UnprocessableEntityHttpException('Xizmat izoh bo\'sh bo\'lishi mumkin emas.');
        }

        if (!is_numeric($amount) || bccomp((string) $amount, '0', 2) <= 0) {
            throw new UnprocessableEntityHttpException('Xizmat summasi musbat son bo\'lishi kerak.');
        }
    }
}
