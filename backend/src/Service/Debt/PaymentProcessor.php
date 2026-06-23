<?php

declare(strict_types=1);

namespace App\Service\Debt;

use App\Entity\ClientMonthlyStatus;
use App\Entity\Debt;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\DebtStatus;
use App\Enum\PayMethod;
use App\Enum\PaymentStatus;
use App\Exception\DebtAlreadyPaidException;
use App\Service\Audit\AuditLogger;
use App\Service\Util\PeriodRangeIterator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function payDebt(int $debtId, PayMethod $method, User $actor): Debt
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            // SELECT FOR UPDATE
            $row = $conn->fetchAssociative(
                'SELECT * FROM debts WHERE id = ? FOR UPDATE',
                [$debtId]
            );

            if ($row === false) {
                $conn->rollBack();
                throw new NotFoundHttpException('Debt not found.');
            }

            if ($row['status'] !== 'active') {
                $conn->rollBack();
                throw new DebtAlreadyPaidException();
            }

            /** @var Debt $debt */
            $debt = $this->em->find(Debt::class, $debtId);
            $this->em->refresh($debt);

            // Mark debt as paid
            $debt->setStatus(DebtStatus::Paid);
            $debt->setPaidAt(new \DateTimeImmutable());
            $debt->setPaidMethod($method);
            $debt->setPaidBy($actor);
            $debt->setUpdatedAt(new \DateTimeImmutable());

            // Create payment record (amount = debt.amount, frontend amount IGNORED)
            $payment = new Payment();
            $payment->setClient($debt->getClient());
            $payment->setDebt($debt);
            $payment->setAmount($debt->getAmount());
            $payment->setPaymentMethod($method);
            $payment->setPeriod($debt->getLastOverduePeriod());
            $payment->setCreatedBy($actor);
            $this->em->persist($payment);

            // UPSERT CMS for all overdue periods
            foreach (PeriodRangeIterator::between($debt->getFirstOverduePeriod(), $debt->getLastOverduePeriod()) as $period) {
                $cms = $this->em->getRepository(ClientMonthlyStatus::class)->findOneBy([
                    'client' => $debt->getClient(),
                    'period' => $period,
                ]);

                if ($cms === null) {
                    $cms = new ClientMonthlyStatus();
                    $cms->setClient($debt->getClient());
                    $cms->setPeriod($period);
                    $cms->setPaymentTypeSnapshot($debt->getPaymentTypeSnapshot());
                    $this->em->persist($cms);
                }

                $cms->setPaymentStatus(PaymentStatus::Paid);
                $cms->setPaymentMethod($method);
                $cms->setDebt($debt);
                $cms->setPaidAt(new \DateTimeImmutable());
            }

            // Update client's last_paid_period to reflect the debt payment
            $client = $debt->getClient();
            $debtLastPeriod = $debt->getLastOverduePeriod();
            if ($client->getLastPaidPeriod() === null || strcmp($debtLastPeriod, $client->getLastPaidPeriod()) > 0) {
                $client->setLastPaidPeriod($debtLastPeriod);
                $client->setUpdatedAt(new \DateTimeImmutable());
            }

            $this->em->flush();
            $conn->commit();

            $this->auditLogger->log($actor, 'debt.paid', 'debt', $debtId, [
                'amount' => $debt->getAmount(),
                'method' => $method->value,
                'client_id' => $debt->getClient()->getId(),
            ]);

            return $debt;
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }
}
