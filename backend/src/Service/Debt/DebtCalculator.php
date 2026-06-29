<?php

declare(strict_types=1);

namespace App\Service\Debt;

use App\Dto\Debt\DetectionReport;
use App\Entity\Client;
use App\Entity\ClientMonthlyStatus;
use App\Entity\Debt;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\ClientStatus;
use App\Enum\DebtStatus;
use App\Enum\NotificationType;
use App\Enum\PayMethod;
use App\Enum\PaymentStatus;
use App\Repository\ClientRepository;
use App\Service\Config\ConfigService;
use App\Service\Notification\NotificationService;
use App\Service\Util\PeriodRangeIterator;
use Doctrine\ORM\EntityManagerInterface;

final class DebtCalculator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
        private readonly ConfigService $configService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function detectNewDebtors(\DateTimeImmutable $today): DetectionReport
    {
        $report = new DetectionReport();

        $currentPeriod = $today->format('Y-m');
        $unitPrice = $this->configService->get('unit_price');

        // Find ALL active clients whose lastPaidPeriod < currentPeriod (i.e. they haven't paid this month yet)
        $clients = $this->clientRepository->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.lastPaidPeriod IS NOT NULL')
            ->andWhere('c.lastPaidPeriod < :currentPeriod')
            ->setParameter('status', ClientStatus::Faol)
            ->setParameter('currentPeriod', $currentPeriod)
            ->getQuery()
            ->getResult();

        $cmsRepo = $this->em->getRepository(ClientMonthlyStatus::class);
        $debtRepo = $this->em->getRepository(Debt::class);

        foreach ($clients as $client) {
            /** @var Client $client */
            $report->processedClientsCount++;

            $lastPaid = $client->getLastPaidPeriod();
            $firstOverdue = (\DateTimeImmutable::createFromFormat('Y-m-d', $lastPaid . '-01'))
                ->modify('+1 month')
                ->format('Y-m');

            $monthlyAmount = bcmul($unitPrice, (string) $client->getProductCount(), 2);

            // ── Phase 1: Try to auto-deduct from balance for each overdue month ──
            $balanceDeductedUpTo = null;
            foreach (PeriodRangeIterator::between($firstOverdue, $currentPeriod) as $period) {
                $cms = $cmsRepo->findOneBy(['client' => $client, 'period' => $period]);

                if ($cms !== null && $cms->getPaymentStatus() === PaymentStatus::Paid) {
                    $balanceDeductedUpTo = $period;
                    continue; // Already paid individually
                }

                // Check if client has enough balance to cover this month
                if (bccomp($client->getBalance(), $monthlyAmount, 2) >= 0) {
                    // Deduct from balance
                    $client->deductBalance($monthlyAmount);

                    // Create or update CMS as paid
                    if ($cms === null) {
                        $cms = new ClientMonthlyStatus();
                        $cms->setClient($client);
                        $cms->setPeriod($period);
                        $this->em->persist($cms);
                    }
                    $cms->setPaymentStatus(PaymentStatus::Paid);
                    $cms->setPaymentMethod(null);
                    $cms->setPaymentTypeSnapshot($client->getPaymentType());
                    $cms->setPaidAt(new \DateTimeImmutable());
                    $cms->setNotes('auto_deduction_from_balance');

                    // Create Payment record for audit trail
                    $payment = new Payment();
                    $payment->setClient($client);
                    $payment->setAmount($monthlyAmount);
                    $payment->setPaymentMethod(PayMethod::Naqt);
                    $payment->setPeriod($period);
                    $payment->setNotes('auto_deduction_from_balance');
                    $this->em->persist($payment);

                    // Update lastPaidPeriod
                    $client->setLastPaidPeriod($period);
                    $client->setUpdatedAt(new \DateTimeImmutable());
                    $balanceDeductedUpTo = $period;
                    $report->balanceDeductedCount = ($report->balanceDeductedCount ?? 0) + 1;
                    continue;
                }

                // Balance insufficient — stop auto-deduction, remaining months become debt
                break;
            }

            // ── Phase 2: Recalculate overdue months after balance deductions ──
            $effectiveLastPaid = $client->getLastPaidPeriod();
            if (strcmp($effectiveLastPaid, $currentPeriod) >= 0) {
                // All months covered by balance — close any existing debt
                $existingDebt = $debtRepo->findOneBy([
                    'client' => $client,
                    'status' => DebtStatus::Active,
                ]);
                if ($existingDebt !== null) {
                    $existingDebt->setStatus(DebtStatus::Paid);
                    $existingDebt->setPaidAt(new \DateTimeImmutable());
                    $existingDebt->setPaidMethod(PayMethod::Naqt);
                    $existingDebt->setUpdatedAt(new \DateTimeImmutable());
                }
                continue;
            }

            // Count remaining unpaid months
            $newFirstOverdue = (\DateTimeImmutable::createFromFormat('Y-m-d', $effectiveLastPaid . '-01'))
                ->modify('+1 month')
                ->format('Y-m');

            $monthsOverdue = 0;
            foreach (PeriodRangeIterator::between($newFirstOverdue, $currentPeriod) as $period) {
                $cms = $cmsRepo->findOneBy(['client' => $client, 'period' => $period]);

                if ($cms !== null && $cms->getPaymentStatus() === PaymentStatus::Paid) {
                    continue; // This month was paid individually, skip
                }

                // Mark CMS as unpaid
                if ($cms === null) {
                    $cms = new ClientMonthlyStatus();
                    $cms->setClient($client);
                    $cms->setPeriod($period);
                    $cms->setPaymentTypeSnapshot($client->getPaymentType());
                    $this->em->persist($cms);
                }
                $cms->setPaymentStatus(PaymentStatus::Unpaid);
                $monthsOverdue++;
            }

            if ($monthsOverdue === 0) {
                continue;
            }

            $totalAmount = bcmul($monthlyAmount, (string) $monthsOverdue, 2);

            // Check for existing active debt
            $existingDebt = $debtRepo->findOneBy([
                'client' => $client,
                'status' => DebtStatus::Active,
            ]);

            if ($existingDebt !== null) {
                // Update existing debt with current overdue info
                $existingDebt->setMonthlyAmount($monthlyAmount);
                $existingDebt->setMonthsOverdue($monthsOverdue);
                $existingDebt->setAmount($totalAmount);
                $existingDebt->setFirstOverduePeriod($newFirstOverdue);
                $existingDebt->setLastOverduePeriod($currentPeriod);
                $existingDebt->setPaymentTypeSnapshot($client->getPaymentType());
                $existingDebt->setDueDate($today);
                $existingDebt->setUpdatedAt(new \DateTimeImmutable());
                $report->incrementedCount++;
            } else {
                // Create new debt
                $debt = new Debt();
                $debt->setClient($client);
                $debt->setMonthlyAmount($monthlyAmount);
                $debt->setMonthsOverdue($monthsOverdue);
                $debt->setAmount($totalAmount);
                $debt->setFirstOverduePeriod($newFirstOverdue);
                $debt->setLastOverduePeriod($currentPeriod);
                $debt->setPaymentTypeSnapshot($client->getPaymentType());
                $debt->setDueDate($today);
                $this->em->persist($debt);
                $report->createdCount++;
            }
        }

        $this->em->flush();

        // Notify admins if new debtors detected
        if ($report->createdCount > 0 || $report->incrementedCount > 0) {
            $this->notifyAdmins($report, $today);
        }

        return $report;
    }

    private function notifyAdmins(DetectionReport $report, \DateTimeImmutable $today): void
    {
        $this->notificationService->notifyAdmins(
            NotificationType::DebtCreated,
            'Yangi qarzdorlar aniqlandi',
            sprintf(
                '%s: %d yangi, %d oshirilgan (%d mijoz tekshirildi)',
                $today->format('Y-m-d'),
                $report->createdCount,
                $report->incrementedCount,
                $report->processedClientsCount,
            ),
            '/debtors',
        );
        $this->notificationService->flush();
    }
}
