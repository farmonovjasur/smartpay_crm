<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Client;
use App\Entity\ClientMonthlyStatus;
use App\Entity\Debt;
use App\Enum\ClientStatus;
use App\Enum\DebtStatus;
use App\Enum\PaymentStatus;
use App\Repository\ClientRepository;
use App\Service\Config\ConfigService;
use App\Service\Util\PeriodRangeIterator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfill ClientMonthlyStatus + Debt rows for every alive, active client whose
 * last_paid_period is older than the current month.
 *
 * Useful after rolling out the simplified "current_month != last_paid → debtor"
 * rule, so that legacy clients created with the previous (anniversary-based)
 * logic become flagged as debtors immediately.
 */
#[AsCommand(
    name: 'app:reconcile-client-debts',
    description: 'Backfill debt records for active clients whose last_paid_period is older than the current month',
)]
final class ReconcileClientDebtsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
        private readonly ConfigService $configService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $today = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent'));
        $currentPeriod = $today->format('Y-m');
        $unitPrice = $this->configService->get('unit_price');
        $cmsRepo = $this->em->getRepository(ClientMonthlyStatus::class);
        $debtRepo = $this->em->getRepository(Debt::class);

        $clients = $this->clientRepository->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.lastPaidPeriod IS NOT NULL')
            ->andWhere('c.lastPaidPeriod < :currentPeriod')
            ->setParameter('status', ClientStatus::Faol)
            ->setParameter('currentPeriod', $currentPeriod)
            ->getQuery()
            ->getResult();

        if (count($clients) === 0) {
            $io->success('Reconcilable clients topilmadi (hammasi joriy oygacha to\'lagan).');
            return Command::SUCCESS;
        }

        $io->section(sprintf('%d ta mijoz uchun qarz hisoblanmoqda…', count($clients)));

        $totalDebtsCreated = 0;
        $totalDebtsUpdated = 0;
        $totalCmsCreated = 0;
        $now = new \DateTimeImmutable();

        foreach ($clients as $client) {
            /** @var Client $client */
            $lastPaid = $client->getLastPaidPeriod();
            $firstOverdue = (\DateTimeImmutable::createFromFormat('Y-m-d', $lastPaid . '-01'))
                ->modify('+1 month')
                ->format('Y-m');

            $monthsOverdue = 0;
            foreach (PeriodRangeIterator::between($firstOverdue, $currentPeriod) as $period) {
                $cms = $cmsRepo->findOneBy(['client' => $client, 'period' => $period]);
                if ($cms === null) {
                    $cms = new ClientMonthlyStatus();
                    $cms->setClient($client);
                    $cms->setPeriod($period);
                    $cms->setPaymentStatus(PaymentStatus::Unpaid);
                    $cms->setPaymentTypeSnapshot($client->getPaymentType());
                    $this->em->persist($cms);
                    $totalCmsCreated++;
                    $monthsOverdue++;
                    continue;
                }
                if ($cms->getPaymentStatus() === PaymentStatus::Paid) {
                    continue;
                }
                $cms->setPaymentStatus(PaymentStatus::Unpaid);
                $monthsOverdue++;
            }

            if ($monthsOverdue === 0) {
                continue;
            }

            $monthlyAmount = bcmul($unitPrice, (string) $client->getProductCount(), 2);
            $totalAmount = bcmul($monthlyAmount, (string) $monthsOverdue, 2);

            $existing = $debtRepo->findOneBy([
                'client' => $client,
                'status' => DebtStatus::Active,
            ]);

            if ($existing !== null) {
                $existing->setMonthlyAmount($monthlyAmount);
                $existing->setMonthsOverdue($monthsOverdue);
                $existing->setAmount($totalAmount);
                $existing->setFirstOverduePeriod($firstOverdue);
                $existing->setLastOverduePeriod($currentPeriod);
                $existing->setPaymentTypeSnapshot($client->getPaymentType());
                $existing->setDueDate($today);
                $existing->setUpdatedAt($now);
                $debt = $existing;
                $totalDebtsUpdated++;
            } else {
                $debt = new Debt();
                $debt->setClient($client);
                $debt->setMonthlyAmount($monthlyAmount);
                $debt->setMonthsOverdue($monthsOverdue);
                $debt->setAmount($totalAmount);
                $debt->setFirstOverduePeriod($firstOverdue);
                $debt->setLastOverduePeriod($currentPeriod);
                $debt->setPaymentTypeSnapshot($client->getPaymentType());
                $debt->setDueDate($today);
                $this->em->persist($debt);
                $totalDebtsCreated++;
            }

            $this->em->flush();

            // Link unpaid CMS rows to the debt
            foreach (PeriodRangeIterator::between($firstOverdue, $currentPeriod) as $period) {
                $cms = $cmsRepo->findOneBy(['client' => $client, 'period' => $period]);
                if ($cms !== null && $cms->getPaymentStatus() === PaymentStatus::Unpaid && $cms->getDebt() === null) {
                    $cms->setDebt($debt);
                }
            }
            $this->em->flush();

            $io->writeln(sprintf(
                '  • #%d %s: %d oy qarz (%s so\'m)',
                $client->getId(),
                $client->getName(),
                $monthsOverdue,
                $totalAmount,
            ));
        }

        $io->success(sprintf(
            'Tugadi. Yangi qarzlar: %d, yangilangan: %d, yangi CMS qatorlar: %d',
            $totalDebtsCreated,
            $totalDebtsUpdated,
            $totalCmsCreated,
        ));

        return Command::SUCCESS;
    }
}
