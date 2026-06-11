<?php

declare(strict_types=1);

namespace App\Service\Debt;

use App\Dto\Debt\DetectionReport;
use App\Entity\Client;
use App\Entity\ClientMonthlyStatus;
use App\Entity\Debt;
use App\Entity\User;
use App\Enum\DebtStatus;
use App\Enum\NotificationType;
use App\Enum\PaymentStatus;
use App\Repository\ClientRepository;
use App\Service\Config\ConfigService;
use App\Service\Notification\NotificationService;
use App\Service\Util\EffectiveDayCalculator;
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

        // Get clients whose anniversary day is today
        $clients = $this->clientRepository->findActiveClientsWithAnniversaryDay($today);

        // Determine previous period
        $prevPeriod = $today->modify('-1 month')->format('Y-m');
        $unitPrice = $this->configService->get('unit_price');

        foreach ($clients as $client) {
            $effectiveDay = EffectiveDayCalculator::compute($today, $client->getServiceDate());
            if ((int) $today->format('d') !== $effectiveDay) {
                continue;
            }

            $report->processedClientsCount++;

            // Check if prev period was paid
            $cms = $this->em->getRepository(ClientMonthlyStatus::class)->findOneBy([
                'client' => $client,
                'period' => $prevPeriod,
            ]);

            if ($cms !== null && $cms->getPaymentStatus() === PaymentStatus::Paid) {
                continue; // Already paid, skip
            }

            // Mark CMS as unpaid
            if ($cms === null) {
                $cms = new ClientMonthlyStatus();
                $cms->setClient($client);
                $cms->setPeriod($prevPeriod);
                $cms->setPaymentTypeSnapshot($client->getPaymentType());
                $this->em->persist($cms);
            }
            $cms->setPaymentStatus(PaymentStatus::Unpaid);

            // Check for existing active debt
            $existingDebt = $this->em->getRepository(Debt::class)->findOneBy([
                'client' => $client,
                'status' => DebtStatus::Active,
            ]);

            $monthlyAmount = bcmul($unitPrice, (string) $client->getProductCount(), 2);

            if ($existingDebt !== null) {
                // Increment
                $existingDebt->setMonthsOverdue($existingDebt->getMonthsOverdue() + 1);
                $existingDebt->setLastOverduePeriod($prevPeriod);
                $existingDebt->setAmount(bcmul($monthlyAmount, (string) $existingDebt->getMonthsOverdue(), 2));
                $existingDebt->setUpdatedAt(new \DateTimeImmutable());
                $report->incrementedCount++;
            } else {
                // Create new debt
                $debt = new Debt();
                $debt->setClient($client);
                $debt->setMonthlyAmount($monthlyAmount);
                $debt->setAmount($monthlyAmount);
                $debt->setMonthsOverdue(1);
                $debt->setFirstOverduePeriod($prevPeriod);
                $debt->setLastOverduePeriod($prevPeriod);
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
