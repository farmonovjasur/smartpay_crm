<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Debt;
use App\Entity\Payment;
use App\Service\Util\PeriodRangeIterator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-payment-history',
    description: 'Fixes old multi-month payment records by distributing them per month.'
)]
class FixPaymentHistoryCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fixing Payment History for Multi-month Debts');

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $debts = $this->em->getRepository(Debt::class)->createQueryBuilder('d')
                ->where('d.monthsOverdue > 1')
                ->andWhere('d.paidAmount > 0')
                ->getQuery()
                ->getResult();

            $io->text(sprintf('Found %d multi-month debts with payments.', count($debts)));
            $fixedCount = 0;

            foreach ($debts as $debt) {
                /** @var Debt $debt */
                $payments = $this->em->getRepository(Payment::class)->findBy(
                    ['debt' => $debt],
                    ['id' => 'ASC']
                );

                if (count($payments) === 0) {
                    continue;
                }

                $monthlyAmount = $debt->getMonthlyAmount();
                
                // Determine if we actually need to split (e.g. if one payment is > monthlyAmount)
                $needsSplit = false;
                foreach ($payments as $p) {
                    if (bccomp($p->getAppliedAmount() ?? '0', $monthlyAmount, 2) > 0) {
                        $needsSplit = true;
                        break;
                    }
                }

                if (!$needsSplit) {
                    continue;
                }

                $io->text(sprintf('Fixing payments for Debt ID %d (Client ID: %d)', $debt->getId(), $debt->getClient()->getId()));

                // Simulate the progressive payment to redistribute
                $simulatedOldPaidAmount = '0.00';

                foreach ($payments as $originalPayment) {
                    /** @var Payment $originalPayment */
                    $actualPayment = $originalPayment->getAppliedAmount() ?? $originalPayment->getAmount();
                    if (bccomp($actualPayment, '0', 2) <= 0) {
                        continue;
                    }

                    $remainingToDistribute = $actualPayment;
                    $currentOldPaid = $simulatedOldPaidAmount;

                    foreach (PeriodRangeIterator::between($debt->getFirstOverduePeriod(), $debt->getLastOverduePeriod()) as $period) {
                        if (bccomp($remainingToDistribute, '0', 2) <= 0) {
                            break;
                        }

                        if (bccomp($currentOldPaid, $monthlyAmount, 2) >= 0) {
                            $currentOldPaid = bcsub($currentOldPaid, $monthlyAmount, 2);
                            continue;
                        }

                        $monthRemaining = bcsub($monthlyAmount, $currentOldPaid, 2);
                        $currentOldPaid = '0.00';

                        $appliedToThisMonth = (bccomp($remainingToDistribute, $monthRemaining, 2) >= 0) ? $monthRemaining : $remainingToDistribute;
                        $remainingToDistribute = bcsub($remainingToDistribute, $appliedToThisMonth, 2);

                        $newPayment = new Payment();
                        $newPayment->setClient($originalPayment->getClient());
                        $newPayment->setDebt($debt);
                        $newPayment->setAmount($appliedToThisMonth);
                        $newPayment->setAppliedAmount($appliedToThisMonth);
                        $newPayment->setPaymentMethod($originalPayment->getPaymentMethod());
                        $newPayment->setPeriod($period);
                        
                        $refClass = new \ReflectionClass(Payment::class);
                        $prop = $refClass->getProperty('paidAt');
                        $prop->setValue($newPayment, $originalPayment->getPaidAt());
                        
                        $newPayment->setCreatedBy($originalPayment->getCreatedBy());
                        $newPayment->setNotes($originalPayment->getNotes());
                        
                        $this->em->persist($newPayment);
                    }

                    $simulatedOldPaidAmount = bcadd($simulatedOldPaidAmount, $actualPayment, 2);
                    $this->em->remove($originalPayment);
                }

                $fixedCount++;
                
                if ($fixedCount % 20 === 0) {
                    $this->em->flush();
                }
            }

            $this->em->flush();
            $conn->commit();

            $io->success(sprintf('Successfully fixed %d debts.', $fixedCount));
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $conn->rollBack();
            $io->error('Error fixing payments: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
