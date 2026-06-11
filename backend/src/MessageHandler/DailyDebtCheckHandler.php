<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DailyDebtCheck;
use App\Service\Debt\DebtCalculator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DailyDebtCheckHandler
{
    public function __construct(
        private readonly DebtCalculator $debtCalculator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DailyDebtCheck $message): void
    {
        $today = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
        $report = $this->debtCalculator->detectNewDebtors($today);

        $this->logger->info('Daily debt check', [
            'created' => $report->createdCount,
            'incremented' => $report->incrementedCount,
            'processed' => $report->processedClientsCount,
        ]);
    }
}
