<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Debt\DebtCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:debt:check', description: 'Run daily debt detection')]
final class DebtCheckRunCommand extends Command
{
    public function __construct(
        private readonly DebtCalculator $debtCalculator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
        $report = $this->debtCalculator->detectNewDebtors($today);

        $output->writeln(sprintf(
            'Debt check complete: %d new, %d incremented, %d processed',
            $report->createdCount,
            $report->incrementedCount,
            $report->processedClientsCount,
        ));

        return Command::SUCCESS;
    }
}
