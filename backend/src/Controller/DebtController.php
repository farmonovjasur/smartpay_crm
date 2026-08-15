<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Debt;
use App\Enum\ClientStatus;
use App\Enum\DebtStatus;
use App\Enum\PayMethod;
use App\Service\Config\ConfigService;
use App\Service\Debt\PaymentProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Qarzdorlar (Debtors) API.
 *
 * Source of truth: clients.last_paid_period < current_period.
 * The `debts` table provides supplementary detail (exact amounts, periods)
 * but is NOT the authority for who is a debtor.
 */
#[Route('/api/debtors')]
final class DebtController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly ConfigService $configService,
    ) {
    }

    #[Route('/export', name: 'debtor_export', methods: ['GET'])]
    public function export(Request $request, \App\Service\Debt\DebtExporter $exporter): Response
    {
        $status = $request->query->get('status', 'active');
        $search = $request->query->get('search', '');
        return $exporter->exportFiltered($status, $search);
    }

    #[Route('', name: 'debtor_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', '20')));
        $status = $request->query->get('status', 'active');
        $search = $request->query->get('search', '');

        $currentPeriod = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))->format('Y-m');

        // For "paid" or "all" filters, we still need the debts table
        if ($status === 'paid' || $status === 'all') {
            return $this->listFromDebtsTable($page, $pageSize, $status, $search);
        }

        // For "active" (default) — source of truth is clients.last_paid_period
        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Client::class, 'c')
            ->where('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.lastPaidPeriod IS NOT NULL')
            ->andWhere('c.lastPaidPeriod < :currentPeriod')
            ->setParameter('status', ClientStatus::Faol)
            ->setParameter('currentPeriod', $currentPeriod)
            ->orderBy('c.id', 'DESC');

        if ($search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.inn LIKE :search OR c.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $clients = $qb->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        // Batch-load existing debts for these clients
        $clientIds = array_map(fn (Client $c) => $c->getId(), $clients);
        $debtsByClientId = $this->loadActiveDebtsForClients($clientIds);

        $unitPrice = $this->configService->get('unit_price');

        $data = array_map(function (Client $client) use ($debtsByClientId, $currentPeriod, $unitPrice) {
            $debt = $debtsByClientId[$client->getId()] ?? null;

            if ($debt !== null) {
                // Use existing debt record data
                return [
                    'id' => $debt->getId(),
                    'client_id' => $client->getId(),
                    'client_name' => $client->getName(),
                    'client_inn' => $client->getInn(),
                    'amount' => $debt->getAmount(),
                    'paid_amount' => $debt->getPaidAmount(),
                    'remaining_amount' => $debt->getRemainingAmount(),
                    'monthly_amount' => $debt->getMonthlyAmount(),
                    'months_overdue' => $debt->getMonthsOverdue(),
                    'first_overdue_period' => $debt->getFirstOverduePeriod(),
                    'last_overdue_period' => $debt->getLastOverduePeriod(),
                    'payment_type_snapshot' => $debt->getPaymentTypeSnapshot()->value,
                    'status' => $debt->getStatus()->value,
                    'due_date' => $debt->getDueDate()->format('Y-m-d'),
                    'paid_at' => $debt->getPaidAt()?->format('c'),
                    'paid_method' => $debt->getPaidMethod()?->value,
                ];
            }

            // No debt record yet (cron hasn't created it) — compute dynamically
            $lastPaid = $client->getLastPaidPeriod();
            $firstOverdue = $this->nextPeriod($lastPaid);
            $monthsOverdue = $this->countMonthsBetween($firstOverdue, $currentPeriod);
            $monthlyAmount = bcmul($unitPrice, (string) $client->getProductCount(), 2);
            $totalAmount = bcmul($monthlyAmount, (string) $monthsOverdue, 2);

            return [
                'id' => null, // No debt record yet
                'client_id' => $client->getId(),
                'client_name' => $client->getName(),
                'client_inn' => $client->getInn(),
                'amount' => $totalAmount,
                'paid_amount' => '0.00',
                'remaining_amount' => $totalAmount,
                'monthly_amount' => $monthlyAmount,
                'months_overdue' => $monthsOverdue,
                'first_overdue_period' => $firstOverdue,
                'last_overdue_period' => $currentPeriod,
                'payment_type_snapshot' => $client->getPaymentType()->value,
                'status' => 'active',
                'due_date' => (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))->format('Y-m-d'),
            ];
        }, $clients);

        return new JsonResponse(['data' => $data, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
    }

    #[Route('/{id}', name: 'debtor_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $debt = $this->em->find(Debt::class, $id);
        if ($debt === null) {
            throw new NotFoundHttpException('Debt not found.');
        }

        $client = $debt->getClient();

        // 1. Debt payments
        $debtPayments = $this->em->getRepository(\App\Entity\Payment::class)->findBy(['debt' => $debt], ['period' => 'ASC']);
        $debtItems = array_map(fn (\App\Entity\Payment $p) => [
            'id' => $p->getId(),
            'period' => $p->getPeriod(),
            'period_label' => $this->formatPeriodLabel($p->getPeriod()),
            'amount' => $p->getAmount(),
            'type' => 'debt',
            'label' => "Qarz yopildi",
            'notes' => $p->getNotes(),
            'paid_at' => $p->getPaidAt()->format('c'),
        ], $debtPayments);

        // 2. Future months covered by client's balance (oldindan to'lov / depozit)
        $prepaidItems = [];
        $unitPrice = $this->configService->get('unit_price');
        $monthlyAmount = bcmul($unitPrice, (string) $client->getProductCount(), 2);
        $clientBalance = $client->getBalance();
        $estimatedPaidUpTo = $client->getLastPaidPeriod() ?? $debt->getLastOverduePeriod();

        if (bccomp($clientBalance, '0', 2) > 0 && bccomp($monthlyAmount, '0', 2) > 0) {
            $estimatedMonths = (int) bcdiv($clientBalance, $monthlyAmount, 0);
            $startDate = ($estimatedPaidUpTo !== null && $estimatedPaidUpTo !== '')
                ? (\DateTimeImmutable::createFromFormat('Y-m-d', $estimatedPaidUpTo . '-01'))->modify('+1 month')
                : new \DateTimeImmutable();

            for ($i = 0; $i < $estimatedMonths; $i++) {
                $periodStr = $startDate->modify("+{$i} months")->format('Y-m');
                $prepaidItems[] = [
                    'type' => 'prepaid',
                    'period' => $periodStr,
                    'period_label' => $this->formatPeriodLabel($periodStr),
                    'amount' => $monthlyAmount,
                    'label' => "Oldindan to'lov (balansdan)",
                ];
                $estimatedPaidUpTo = $periodStr;
            }
        }

        $totalTransactionAmount = bcadd($debt->getPaidAmount(), $clientBalance, 2);
        $distributionItems = array_merge($debtItems, $prepaidItems);

        $receipt = [
            'receipt_id' => sprintf('CHK-%s-%06d', ($debt->getPaidAt() ?? new \DateTimeImmutable())->format('Ymd'), $debt->getId()),
            'paid_at' => ($debt->getPaidAt() ?? new \DateTimeImmutable())->format('c'),
            'payment_method' => $debt->getPaidMethod()?->value ?? 'naqt',
            'payment_method_label' => $debt->getPaidMethod() === PayMethod::Fakt ? 'Fakt (online)' : 'Naqt',
            'total_amount' => $totalTransactionAmount,
            'debt_amount_paid' => $debt->getPaidAmount(),
            'prepaid_amount' => $clientBalance,
            'balance_added' => $clientBalance,
            'client' => [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'inn' => $client->getInn(),
                'phone' => $client->getPhone(),
                'balance' => $client->getBalance(),
                'last_paid_period' => $client->getLastPaidPeriod(),
                'last_paid_period_label' => $client->getLastPaidPeriod() ? $this->formatPeriodLabel($client->getLastPaidPeriod()) : null,
            ],
            'debt_items' => $debtItems,
            'prepaid_items' => $prepaidItems,
            'balance_item' => bccomp($clientBalance, '0', 2) > 0 ? [
                'amount' => $clientBalance,
                'label' => "Joriy balans (depozit)",
            ] : null,
            'summary' => [
                'debt_remaining' => $debt->getRemainingAmount(),
                'new_balance' => $client->getBalance(),
                'paid_up_to' => $estimatedPaidUpTo,
                'paid_up_to_label' => $estimatedPaidUpTo ? $this->formatPeriodLabel($estimatedPaidUpTo) : null,
                'months_debt_closed' => count($debtItems),
                'months_prepaid' => count($prepaidItems),
                'created_by' => $debt->getPaidBy()?->getName() ?? 'Admin',
            ],
        ];

        return new JsonResponse(['data' => [
            'id' => $debt->getId(),
            'client_id' => $client->getId(),
            'client_name' => $client->getName(),
            'client_inn' => $client->getInn(),
            'client_phone' => $client->getPhone(),
            'client_last_paid_period' => $client->getLastPaidPeriod(),
            'amount' => $debt->getAmount(),
            'paid_amount' => $debt->getPaidAmount(),
            'remaining_amount' => $debt->getRemainingAmount(),
            'monthly_amount' => $debt->getMonthlyAmount(),
            'months_overdue' => $debt->getMonthsOverdue(),
            'first_overdue_period' => $debt->getFirstOverduePeriod(),
            'last_overdue_period' => $debt->getLastOverduePeriod(),
            'payment_type_snapshot' => $debt->getPaymentTypeSnapshot()->value,
            'status' => $debt->getStatus()->value,
            'balance' => $client->getBalance(),
            'paid_at' => $debt->getPaidAt()?->format('c'),
            'paid_method' => $debt->getPaidMethod()?->value,
            'advance_amount' => $clientBalance,
            'advance_payments' => $prepaidItems,
            'total_transaction_amount' => $totalTransactionAmount,
            'distribution_items' => $distributionItems,
            'receipt' => $receipt,
        ]]);
    }

    #[Route('/client/{clientId}/pay', name: 'debtor_pay_by_client', methods: ['POST'], requirements: ['clientId' => '\d+'])]
    public function payByClient(
        int $clientId,
        Request $request,
        \App\Service\Debt\DebtCalculator $calculator,
        \App\Repository\ClientRepository $clientRepo
    ): JsonResponse {
        $client = $clientRepo->find($clientId);
        if (!$client) {
            throw new NotFoundHttpException('Client not found.');
        }

        // Generate the debt dynamically for this specific client to ensure we have a Debt record
        $calculator->detectForClient($client, new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')));
        $this->em->flush(); // flush the changes made by detectForClient

        // Find the created or existing debt
        $debt = $this->em->getRepository(Debt::class)->findOneBy([
            'client' => $client,
            'status' => [\App\Enum\DebtStatus::Active, \App\Enum\DebtStatus::Partial]
        ]);

        if (!$debt) {
            return new JsonResponse(['error' => "Qarz topilmadi yoki mijoz balansi orqali yopildi."], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pay($debt->getId(), $request);
    }

    #[Route('/{id}/pay', name: 'debtor_pay', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function pay(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $method = $data['method'] ?? '';
        $amount = $data['amount'] ?? null;

        if (!in_array($method, ['fakt', 'naqt'], true)) {
            return new JsonResponse(['error' => 'method must be fakt or naqt'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($amount === null || $amount === '') {
            return new JsonResponse(['error' => "To'lov summasi kiritilishi shart"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $result = $this->paymentProcessor->payDebt($id, (string) $amount, PayMethod::from($method), $actor);

        /** @var \App\Entity\Debt $debt */
        $debt = $result['debt'];

        $message = $result['fully_paid']
            ? "Qarz to'liq to'landi."
            : sprintf("Qisman to'lov qabul qilindi. Qolgan qarz: %s so'm.", number_format((float) $result['remaining'], 0, '.', ' '));

        return new JsonResponse([
            'message' => $message,
            'data' => [
                'id' => $debt->getId(),
                'status' => $debt->getStatus()->value,
                'paid_method' => $debt->getPaidMethod()?->value,
                'amount' => $debt->getAmount(),
                'paid_amount' => $debt->getPaidAmount(),
                'remaining_amount' => $result['remaining'],
                'overpayment' => $result['overpayment'],
                'balance' => $result['balance'],
                'fully_paid' => $result['fully_paid'],
                'months_closed' => $result['months_closed'],
                'advance_months_closed' => $result['advance_months_closed'] ?? 0,
                'receipt' => $result['receipt'] ?? null,
            ],
        ]);
    }

    // ─── Private helpers ───────────────────────────────────────────────

    /**
     * Fallback for "paid" or "all" status filters — these still need the debts table
     * because paid debts have no computed equivalent from last_paid_period.
     */
    private function listFromDebtsTable(int $page, int $pageSize, string $status, string $search): JsonResponse
    {
        $qb = $this->em->createQueryBuilder()
            ->select('d')
            ->from(Debt::class, 'd')
            ->innerJoin('d.client', 'c')
            ->orderBy('d.id', 'DESC');

        if ($status !== 'all') {
            $qb->andWhere('d.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.inn LIKE :search OR c.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $total = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();

        $debts = $qb->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (Debt $d) => [
            'id' => $d->getId(),
            'client_id' => $d->getClient()->getId(),
            'client_name' => $d->getClient()->getName(),
            'client_inn' => $d->getClient()->getInn(),
            'amount' => $d->getAmount(),
            'paid_amount' => $d->getPaidAmount(),
            'remaining_amount' => $d->getRemainingAmount(),
            'monthly_amount' => $d->getMonthlyAmount(),
            'months_overdue' => $d->getMonthsOverdue(),
            'first_overdue_period' => $d->getFirstOverduePeriod(),
            'last_overdue_period' => $d->getLastOverduePeriod(),
            'payment_type_snapshot' => $d->getPaymentTypeSnapshot()->value,
            'status' => $d->getStatus()->value,
            'due_date' => $d->getDueDate()->format('Y-m-d'),
            'paid_at' => $d->getPaidAt()?->format('c'),
            'paid_method' => $d->getPaidMethod()?->value,
        ], $debts);

        return new JsonResponse(['data' => $data, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
    }

    /**
     * Batch-load active debts for a set of client IDs.
     *
     * @param int[] $clientIds
     * @return array<int, Debt> keyed by client_id
     */
    private function loadActiveDebtsForClients(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $debts = $this->em->createQueryBuilder()
            ->select('d')
            ->from(Debt::class, 'd')
            ->where('d.client IN (:ids)')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('ids', $clientIds)
            ->setParameter('statuses', [DebtStatus::Active, DebtStatus::Partial])
            ->getQuery()
            ->getResult();

        $map = [];
        /** @var Debt $debt */
        foreach ($debts as $debt) {
            $map[$debt->getClient()->getId()] = $debt;
        }

        return $map;
    }

    private function nextPeriod(string $period): string
    {
        return (\DateTimeImmutable::createFromFormat('Y-m-d', $period . '-01'))
            ->modify('+1 month')
            ->format('Y-m');
    }

    private function countMonthsBetween(string $from, string $to): int
    {
        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $from . '-01');
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', $to . '-01');
        $diff = $start->diff($end);

        return max(1, ($diff->y * 12) + $diff->m + 1);
    }
}
