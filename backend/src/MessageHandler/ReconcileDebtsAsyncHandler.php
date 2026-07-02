<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ReconcileDebtsAsync;
use App\Service\Debt\DebtCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles the async debt reconciliation message.
 * Uses Symfony Lock to ensure only one reconciliation runs per day,
 * regardless of how many messages are dispatched.
 */
#[AsMessageHandler]
final class ReconcileDebtsAsyncHandler
{
    public function __construct(
        private readonly DebtCalculator $debtCalculator,
        private readonly LockFactory $lockFactory,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReconcileDebtsAsync $message): void
    {
        $lock = $this->lockFactory->createLock(
            'daily_debt_reconcile_' . $message->date,
            ttl: 300, // 5 minutes max execution time
        );

        // Non-blocking acquire — if another process already has it, skip
        if (!$lock->acquire(blocking: false)) {
            $this->logger->debug('ReconcileDebtsAsyncHandler: skipped (lock held)', [
                'date' => $message->date,
            ]);
            return;
        }

        try {
            $today = new \DateTimeImmutable($message->date, new \DateTimeZone('Asia/Tashkent'));
            $report = $this->debtCalculator->detectNewDebtors($today);

            $this->logger->info('ReconcileDebtsAsyncHandler: completed', [
                'date' => $message->date,
                'created' => $report->createdCount,
                'incremented' => $report->incrementedCount,
                'processed' => $report->processedClientsCount,
            ]);

            // Record completion for health monitoring
            $this->recordLastCheckDate($message->date);
        } finally {
            $lock->release();
        }
    }

    private function recordLastCheckDate(string $date): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                "INSERT INTO app_config (`key`, `value`) VALUES ('last_debt_check_date', :date)
                 ON DUPLICATE KEY UPDATE `value` = :date",
                ['date' => $date],
            );
        } catch (\Throwable $e) {
            $this->logger->warning('ReconcileDebtsAsyncHandler: failed to record date', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
