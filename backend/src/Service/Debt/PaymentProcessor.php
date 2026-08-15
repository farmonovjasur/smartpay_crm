<?php

declare(strict_types=1);

namespace App\Service\Debt;

use App\Entity\Client;
use App\Entity\ClientMonthlyStatus;
use App\Entity\Debt;
use App\Entity\Payment;
use App\Entity\Prepayment;
use App\Entity\User;
use App\Enum\DebtStatus;
use App\Enum\PayMethod;
use App\Enum\PaymentStatus;
use App\Exception\DebtAlreadyPaidException;
use App\Service\Audit\AuditLogger;
use App\Service\Config\ConfigService;
use App\Service\Util\PeriodRangeIterator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Qisman to'lov va ortiqcha to'lov taqsimotini (FIFO) qo'llab-quvvatlaydigan to'lov protsessori.
 *
 * Biznes qoidalar:
 * - Minimal to'lov: 1 000 so'm
 * - Oylar FIFO tartibda yopiladi (eng eskidan boshlab)
 * - Qarzdan ortgan summa kelgusi oylarga oldindan to'lov sifatida taqsimlanadi
 * - Oylarga bo'linmay qolgan qoldiq mijoz balansiga depozit sifatida tushadi
 * - Har bir to'lov alohida Payment record yaratadi (audit trail)
 * - Batafsil to'lov cheki (receipt) strukturasi qaytariladi
 */
final class PaymentProcessor
{
    /** @var string Minimal to'lov summasi (so'mda) */
    private const MIN_PAYMENT_AMOUNT = '1000.00';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
        private readonly ConfigService $configService,
    ) {
    }

    /**
     * Qarzni to'liq yoki qisman to'lash va ortiqcha summani kelgusi oylarga taqsimlash.
     *
     * @return array{
     *     debt: Debt,
     *     paid: string,
     *     remaining: string,
     *     overpayment: string,
     *     balance: string,
     *     fully_paid: bool,
     *     months_closed: int,
     *     advance_months_closed: int,
     *     receipt: array<string, mixed>,
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

            // ── 2. FIFO tartibda qarz oylarini yopish ──
            $monthsClosed = $this->closeMonthsFIFO($debt, $method, $fullyPaid);

            // ── 3. lastPaidPeriod ni yangilash (yopilgan oylar asosida) ──
            if ($monthsClosed > 0) {
                $this->updateLastPaidPeriod($debt, $client);
            }

            // ── 4. Payment record yaratish (qarz oylari bo'yicha taqsimlangan) ──
            $notes = [];
            if (!$fullyPaid) {
                $notes[] = sprintf('qisman_tolov: %s/%s', $debt->getPaidAmount(), $debt->getAmount());
            }
            if (bccomp($overpayment, '0', 2) > 0) {
                $notes[] = sprintf('ortiqcha: %s UZS taqsimotga yo\'naltirildi', $overpayment);
            }
            $notesStr = empty($notes) ? null : implode(' | ', $notes);
            $debtItems = $this->createDistributedPayments($debt, $actualPayment, $method, $actor, $notesStr);

            // ── 5. Ortiqcha summa to'g'ridan-to'g'ri mijoz balansiga (depozit) tushadi ──
            $prepaidItems = [];
            $estimatedMonthsCount = 0;
            $unitPrice = $this->configService->get('unit_price');
            $monthlyAmount = bcmul($unitPrice, (string) $client->getProductCount(), 2);
            $estimatedPaidUpTo = $client->getLastPaidPeriod();

            if (bccomp($overpayment, '0', 2) > 0) {
                // Balansga to'liq qo'shish
                $client->addBalance($overpayment);
                $client->setUpdatedAt(new \DateTimeImmutable());

                $prepayment = new Prepayment();
                $prepayment->setClient($client);
                $prepayment->setAmount($overpayment);
                $prepayment->setMethod($method);
                $prepayment->setPaidAt(new \DateTimeImmutable());
                $prepayment->setNotes('qarzdan_ortiqcha_tolov');
                $prepayment->setCreatedBy($actor);
                $this->em->persist($prepayment);

                // Chekda aks ettirish uchun balans necha oyga yetishini hisoblash (taxminiy kelgusi oylar)
                if (bccomp($monthlyAmount, '0', 2) > 0) {
                    $estimatedMonthsCount = (int) bcdiv($overpayment, $monthlyAmount, 0);
                    $currentLastPaid = $client->getLastPaidPeriod() ?? $debt->getLastOverduePeriod();
                    $startDate = ($currentLastPaid !== null && $currentLastPaid !== '')
                        ? (\DateTimeImmutable::createFromFormat('Y-m-d', $currentLastPaid . '-01'))->modify('+1 month')
                        : new \DateTimeImmutable();

                    for ($i = 0; $i < $estimatedMonthsCount; $i++) {
                        $periodStr = $startDate->modify("+{$i} months")->format('Y-m');
                        $prepaidItems[] = [
                            'type' => 'prepaid',
                            'period' => $periodStr,
                            'period_label' => $this->formatPeriodLabel($periodStr),
                            'amount' => $monthlyAmount,
                            'label' => "Oldindan to'lov (balansga)",
                        ];
                        $estimatedPaidUpTo = $periodStr;
                    }
                }
            }

            $this->em->flush();
            $conn->commit();

            // ── 6. Audit log ──
            $this->auditLogger->log($actor, 'debt.payment', 'debt', $debtId, [
                'amount_requested' => $amount,
                'amount_applied' => $actualPayment,
                'overpayment' => $overpayment,
                'balance_added' => $overpayment,
                'remaining' => $debt->getRemainingAmount(),
                'status' => $debt->getStatus()->value,
                'method' => $method->value,
                'client_id' => $client->getId(),
                'months_closed' => $monthsClosed,
                'fully_paid' => $fullyPaid,
            ]);

            // ── 7. Batafsil chek (receipt) tuzilishi ──
            $receipt = [
                'receipt_id' => sprintf('CHK-%s-%06d', (new \DateTimeImmutable())->format('Ymd'), $debtId),
                'paid_at' => (new \DateTimeImmutable())->format('c'),
                'payment_method' => $method->value,
                'payment_method_label' => $method === PayMethod::Fakt ? 'Fakt (online)' : 'Naqt',
                'total_amount' => $amount,
                'debt_amount_paid' => $actualPayment,
                'prepaid_amount' => $overpayment,
                'balance_added' => $overpayment,
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
                'balance_item' => bccomp($overpayment, '0', 2) > 0 ? [
                    'amount' => $overpayment,
                    'label' => "Balansga tushgan summa",
                ] : null,
                'summary' => [
                    'debt_remaining' => $debt->getRemainingAmount(),
                    'new_balance' => $client->getBalance(),
                    'paid_up_to' => $estimatedPaidUpTo,
                    'paid_up_to_label' => $estimatedPaidUpTo ? $this->formatPeriodLabel($estimatedPaidUpTo) : null,
                    'months_debt_closed' => $monthsClosed,
                    'months_prepaid' => $estimatedMonthsCount,
                    'created_by' => $actor->getName(),
                ],
            ];

            return [
                'debt' => $debt,
                'paid' => $actualPayment,
                'remaining' => $debt->getRemainingAmount(),
                'overpayment' => $overpayment,
                'balance' => $client->getBalance(),
                'fully_paid' => $fullyPaid,
                'months_closed' => $monthsClosed,
                'advance_months_closed' => $estimatedMonthsCount,
                'receipt' => $receipt,
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
    private function updateLastPaidPeriod(Debt $debt, Client $client): void
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
     * Kelgusi oylarni oldindan to'lov (overpayment) hisobidan FIFO tartibda yopish.
     *
     * @return array{
     *     prepaid_items: array<array<string, mixed>>,
     *     months_closed: int,
     *     remaining_overpayment: string,
     * }
     */
    private function advanceMonthsFIFO(Client $client, string $overpayment, string $monthlyAmount, PayMethod $method, User $actor): array
    {
        $cmsRepo = $this->em->getRepository(ClientMonthlyStatus::class);
        $currentLastPaid = $client->getLastPaidPeriod();
        $now = new \DateTimeImmutable();
        $prepaidItems = [];
        $monthsClosed = 0;
        $remainingOverpayment = $overpayment;

        if ($currentLastPaid === null || $currentLastPaid === '') {
            $startDate = new \DateTimeImmutable();
            $nextPeriod = $startDate->format('Y-m');
        } else {
            $lastPaidDate = \DateTimeImmutable::createFromFormat('Y-m-d', $currentLastPaid . '-01');
            $nextPeriod = $lastPaidDate->modify('+1 month')->format('Y-m');
        }

        while (bccomp($remainingOverpayment, $monthlyAmount, 2) >= 0) {
            $cms = $cmsRepo->findOneBy([
                'client' => $client,
                'period' => $nextPeriod,
            ]);

            if ($cms === null) {
                $cms = new ClientMonthlyStatus();
                $cms->setClient($client);
                $cms->setPeriod($nextPeriod);
                $cms->setPaymentTypeSnapshot($client->getPaymentType());
                $this->em->persist($cms);
            }
            $cms->setPaymentStatus(PaymentStatus::Paid);
            $cms->setPaymentMethod($method);
            $cms->setPaidAt($now);
            $cms->setNotes('oldindan_tolov');

            $payment = new Payment();
            $payment->setClient($client);
            $payment->setAmount($monthlyAmount);
            $payment->setAppliedAmount($monthlyAmount);
            $payment->setPaymentMethod($method);
            $payment->setPeriod($nextPeriod);
            $payment->setCreatedBy($actor);
            $payment->setNotes('oldindan_tolov');
            $this->em->persist($payment);

            $prepaidItems[] = [
                'type' => 'prepaid',
                'period' => $nextPeriod,
                'period_label' => $this->formatPeriodLabel($nextPeriod),
                'amount' => $monthlyAmount,
                'label' => "Oldindan to'lov",
            ];

            $client->setLastPaidPeriod($nextPeriod);
            $client->setUpdatedAt($now);

            $remainingOverpayment = bcsub($remainingOverpayment, $monthlyAmount, 2);
            $monthsClosed++;

            $nextDate = \DateTimeImmutable::createFromFormat('Y-m-d', $nextPeriod . '-01');
            $nextPeriod = $nextDate->modify('+1 month')->format('Y-m');
        }

        return [
            'prepaid_items' => $prepaidItems,
            'months_closed' => $monthsClosed,
            'remaining_overpayment' => $remainingOverpayment,
        ];
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

        $this->createDistributedPayments($debt, $actualPayment, $method, $actor, 'auto_deduction_from_balance');

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

    /**
     * @return array<array<string, mixed>>
     */
    private function createDistributedPayments(Debt $debt, string $actualPayment, PayMethod $method, ?User $actor, ?string $notes): array
    {
        $items = [];
        if (bccomp($actualPayment, '0', 2) <= 0) {
            return $items;
        }

        $monthlyAmount = $debt->getMonthlyAmount();
        $oldPaidAmount = bcsub($debt->getPaidAmount(), $actualPayment, 2);
        $remainingToDistribute = $actualPayment;

        foreach (\App\Service\Util\PeriodRangeIterator::between($debt->getFirstOverduePeriod(), $debt->getLastOverduePeriod()) as $period) {
            if (bccomp($remainingToDistribute, '0', 2) <= 0) {
                break;
            }

            if (bccomp($oldPaidAmount, $monthlyAmount, 2) >= 0) {
                $oldPaidAmount = bcsub($oldPaidAmount, $monthlyAmount, 2);
                continue;
            }

            $monthRemaining = bcsub($monthlyAmount, $oldPaidAmount, 2);
            $oldPaidAmount = '0.00';

            $appliedToThisMonth = (bccomp($remainingToDistribute, $monthRemaining, 2) >= 0) ? $monthRemaining : $remainingToDistribute;
            $remainingToDistribute = bcsub($remainingToDistribute, $appliedToThisMonth, 2);

            $payment = new Payment();
            $payment->setClient($debt->getClient());
            $payment->setDebt($debt);
            $payment->setAmount($appliedToThisMonth);
            $payment->setAppliedAmount($appliedToThisMonth);
            $payment->setPaymentMethod($method);
            $payment->setPeriod($period);
            $payment->setCreatedBy($actor);
            if ($notes !== null) {
                $payment->setNotes($notes);
            }
            $this->em->persist($payment);

            $items[] = [
                'type' => 'debt',
                'period' => $period,
                'period_label' => $this->formatPeriodLabel($period),
                'amount' => $appliedToThisMonth,
                'label' => (bccomp($appliedToThisMonth, $monthlyAmount, 2) >= 0) ? "Qarz yopildi" : "Qisman qarz to'lovi",
            ];
        }

        return $items;
    }

    private function formatPeriodLabel(string $period): string
    {
        $months = [
            '01' => 'Yanvar', '02' => 'Fevral', '03' => 'Mart',
            '04' => 'Aprel', '05' => 'May', '06' => 'Iyun',
            '07' => 'Iyul', '08' => 'Avgust', '09' => 'Sentyabr',
            '10' => 'Oktyabr', '11' => 'Noyabr', '12' => 'Dekabr',
        ];
        $parts = explode('-', $period);
        if (count($parts) === 2 && isset($months[$parts[1]])) {
            return $months[$parts[1]] . ' ' . $parts[0];
        }
        return $period;
    }
}
