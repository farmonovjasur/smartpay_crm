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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Qisman to'lovni qo'llab-quvvatlaydigan to'lov protsessori.
 *
 * Biznes qoidalar:
 * - Minimal to'lov: 1 000 so'm
 * - Oylar FIFO tartibda yopiladi (eng eskidan boshlab)
 * - Ortiqcha summa mijoz balansiga tushadi
 * - Har bir to'lov alohida Payment record yaratadi (audit trail)
 */
final class PaymentProcessor
{
    /** @var string Minimal to'lov summasi (so'mda) */
    private const MIN_PAYMENT_AMOUNT = '1000.00';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Qarzni to'liq yoki qisman to'lash.
     *
     * @return array{
     *     debt: Debt,
     *     paid: string,
     *     remaining: string,
     *     overpayment: string,
     *     balance: string,
     *     fully_paid: bool,
     *     months_closed: int,
     * }
     */
    public function payDebt(int $debtId, string $amount, PayMethod $method, User $actor): array
    {
        // Validatsiya: summa raqam va minimal chegaradan yuqori bo'lishi kerak
        if (!is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
            throw new UnprocessableEntityHttpException("To'lov summasi musbat son bo'lishi kerak.");
        }

        if (bccomp($amount, self::MIN_PAYMENT_AMOUNT, 2) < 0) {
            throw new UnprocessableEntityHttpException(
                sprintf("Minimal to'lov summasi %s so'm.", number_format((float) self::MIN_PAYMENT_AMOUNT, 0, '.', ' '))
            );
        }

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            // SELECT FOR UPDATE — concurrency xavfsizligi
            $row = $conn->fetchAssociative(
                'SELECT * FROM debts WHERE id = ? FOR UPDATE',
                [$debtId]
            );

            if ($row === false) {
                $conn->rollBack();
                throw new NotFoundHttpException('Debt not found.');
            }

            if ($row['status'] === 'paid') {
                $conn->rollBack();
                throw new DebtAlreadyPaidException();
            }

            /** @var Debt $debt */
            $debt = $this->em->find(Debt::class, $debtId);
            $this->em->refresh($debt);

            $client = $debt->getClient();
            $remaining = $debt->getRemainingAmount();

            // Amaliy to'lov summasi — qarz qoldig'idan ortiq bo'lsa, faqat qoldiqni yopamiz
            $actualPayment = (bccomp($amount, $remaining, 2) >= 0) ? $remaining : $amount;
            $overpayment = (bccomp($amount, $remaining, 2) > 0)
                ? bcsub($amount, $remaining, 2)
                : '0.00';
            $fullyPaid = bccomp($amount, $remaining, 2) >= 0;

            // ── 1. Debt'ga to'langan summani qo'shish va oxirgi to'lov ma'lumotlarini yangilash ──
            $debt->addPaidAmount($actualPayment);
            $debt->setUpdatedAt(new \DateTimeImmutable());
            $debt->setPaidAt(new \DateTimeImmutable());
            $debt->setPaidMethod($method);
            $debt->setPaidBy($actor);

            if ($fullyPaid) {
                $debt->setStatus(DebtStatus::Paid);
            } else {
                $debt->setStatus(DebtStatus::Partial);
            }

            // ── 2. FIFO tartibda oylarni yopish ──
            $monthsClosed = $this->closeMonthsFIFO($debt, $method, $fullyPaid);

            // ── 3. lastPaidPeriod ni yangilash (yopilgan oylar asosida) ──
            if ($monthsClosed > 0) {
                $this->updateLastPaidPeriod($debt, $client);
            }

            // ── 4. Payment record yaratish ──
            $payment = new Payment();
            $payment->setClient($client);
            $payment->setDebt($debt);
            $payment->setAmount($amount);
            $payment->setAppliedAmount($actualPayment);
            $payment->setPaymentMethod($method);
            $payment->setPeriod($debt->getLastOverduePeriod());
            $payment->setCreatedBy($actor);
            
            $notes = [];
            if (!$fullyPaid) {
                $notes[] = sprintf('qisman_tolov: %s/%s', $debt->getPaidAmount(), $debt->getAmount());
            }
            if (bccomp($overpayment, '0', 2) > 0) {
                $notes[] = sprintf('ortiqcha: %s UZS balansga tushdi', $overpayment);
            }
            if (!empty($notes)) {
                $payment->setNotes(implode(' | ', $notes));
            }
            $this->em->persist($payment);

            // ── 5. Ortiqcha summa → balansga ──
            if (bccomp($overpayment, '0', 2) > 0) {
                $client->addBalance($overpayment);
                $client->setUpdatedAt(new \DateTimeImmutable());
            }

            $this->em->flush();
            $conn->commit();

            // ── 6. Audit log ──
            $this->auditLogger->log($actor, 'debt.payment', 'debt', $debtId, [
                'amount_requested' => $amount,
                'amount_applied' => $actualPayment,
                'overpayment' => $overpayment,
                'remaining' => $debt->getRemainingAmount(),
                'status' => $debt->getStatus()->value,
                'method' => $method->value,
                'client_id' => $client->getId(),
                'months_closed' => $monthsClosed,
                'fully_paid' => $fullyPaid,
            ]);

            return [
                'debt' => $debt,
                'paid' => $actualPayment,
                'remaining' => $debt->getRemainingAmount(),
                'overpayment' => $overpayment,
                'balance' => $client->getBalance(),
                'fully_paid' => $fullyPaid,
                'months_closed' => $monthsClosed,
            ];
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * FIFO tartibda oylarni yopish.
     *
     * Eng eski oydan boshlab, agar to'langan summa butun bir oy narxini
     * qoplasa — o'sha oy "paid" bo'ladi. To'liq to'langanda barcha oylar yopiladi.
     *
     * @return int Yopilgan oylar soni
     */
    private function closeMonthsFIFO(Debt $debt, PayMethod $method, bool $fullyPaid): int
    {
        $cmsRepo = $this->em->getRepository(ClientMonthlyStatus::class);
        $monthlyAmount = $debt->getMonthlyAmount();
        $paidAmount = $debt->getPaidAmount();
        $now = new \DateTimeImmutable();
        $monthsClosed = 0;

        // Har bir davr uchun tekshiramiz
        $coveredAmount = '0.00';
        foreach (PeriodRangeIterator::between($debt->getFirstOverduePeriod(), $debt->getLastOverduePeriod()) as $period) {
            $cms = $cmsRepo->findOneBy([
                'client' => $debt->getClient(),
                'period' => $period,
            ]);

            // Allaqachon to'langan oy — o'tkazib yuborish
            if ($cms !== null && $cms->getPaymentStatus() === PaymentStatus::Paid) {
                continue;
            }

            $coveredAmount = bcadd($coveredAmount, $monthlyAmount, 2);

            // To'liq to'langanda barcha oylar yopiladi
            if ($fullyPaid) {
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
                $cms->setPaidAt($now);
                $monthsClosed++;
                continue;
            }

            // Qisman to'lov: faqat to'langan summa butun oyni qoplasa
            if (bccomp($paidAmount, $coveredAmount, 2) >= 0) {
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
                $cms->setPaidAt($now);
                $monthsClosed++;
            } else {
                // Bu oy to'liq qoplanmagan — to'xtaymiz
                break;
            }
        }

        return $monthsClosed;
    }

    /**
     * Yopilgan oylar asosida lastPaidPeriod ni yangilash.
     *
     * Faqat eng eski oydan ketma-ket yopilgan oylar hisoblanadi — oradagi
     * "bo'shliq" topilsa, o'sha joyda to'xtaydi.
     */
    private function updateLastPaidPeriod(Debt $debt, \App\Entity\Client $client): void
    {
        $cmsRepo = $this->em->getRepository(ClientMonthlyStatus::class);
        $latestPaidPeriod = null;

        foreach (PeriodRangeIterator::between($debt->getFirstOverduePeriod(), $debt->getLastOverduePeriod()) as $period) {
            $cms = $cmsRepo->findOneBy([
                'client' => $client,
                'period' => $period,
            ]);

            if ($cms !== null && $cms->getPaymentStatus() === PaymentStatus::Paid) {
                $latestPaidPeriod = $period;
            } else {
                // Ketma-ket emas — to'xtash
                break;
            }
        }

        if ($latestPaidPeriod !== null) {
            if ($client->getLastPaidPeriod() === null || strcmp($latestPaidPeriod, $client->getLastPaidPeriod()) > 0) {
                $client->setLastPaidPeriod($latestPaidPeriod);
                $client->setUpdatedAt(new \DateTimeImmutable());
            }
        }
    }

    /**
     * Mijoz balansidagi pulni qarzga avtomatik yo'naltirish.
     */
    public function applyBalanceToDebt(Debt $debt, ?User $actor = null): void
    {
        $client = $debt->getClient();
        $balance = $client->getBalance();

        if (bccomp($balance, '0', 2) <= 0) {
            return;
        }

        $remaining = $debt->getRemainingAmount();
        if (bccomp($remaining, '0', 2) <= 0) {
            return;
        }

        $actualPayment = (bccomp($balance, $remaining, 2) >= 0) ? $remaining : $balance;
        $fullyPaid = bccomp($balance, $remaining, 2) >= 0;

        $method = PayMethod::Naqt;

        $debt->addPaidAmount($actualPayment);
        $debt->setUpdatedAt(new \DateTimeImmutable());
        $debt->setPaidAt(new \DateTimeImmutable());
        $debt->setPaidMethod($method);
        
        if ($actor !== null) {
            $debt->setPaidBy($actor);
        }

        if ($fullyPaid) {
            $debt->setStatus(DebtStatus::Paid);
        } else {
            $debt->setStatus(DebtStatus::Partial);
        }

        $monthsClosed = $this->closeMonthsFIFO($debt, $method, $fullyPaid);

        if ($monthsClosed > 0) {
            $this->updateLastPaidPeriod($debt, $client);
        }

        $payment = new Payment();
        $payment->setClient($client);
        $payment->setDebt($debt);
        $payment->setAmount($actualPayment);
        $payment->setAppliedAmount($actualPayment);
        $payment->setPaymentMethod($method);
        $payment->setPeriod($debt->getLastOverduePeriod());
        $payment->setCreatedBy($actor);
        $payment->setNotes('auto_deduction_from_balance');
        $this->em->persist($payment);

        $client->deductBalance($actualPayment);

        $this->em->flush();

        $this->auditLogger->log($actor, 'debt.auto_payment', 'debt', $debt->getId(), [
            'amount_applied' => $actualPayment,
            'remaining' => $debt->getRemainingAmount(),
            'status' => $debt->getStatus()->value,
            'months_closed' => $monthsClosed,
            'fully_paid' => $fullyPaid,
        ]);
    }
}
