<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use Doctrine\ORM\EntityManagerInterface;

final class DashboardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function stats(): array
    {
        $conn = $this->em->getConnection();

        $activeClients = (int) $conn->fetchOne("SELECT COUNT(*) FROM clients WHERE status='faol' AND deleted_at IS NULL");
        $debtorsCount = (int) $conn->fetchOne("SELECT COUNT(*) FROM debts WHERE status='active'");
        $totalDebt = $conn->fetchOne("SELECT COALESCE(SUM(amount), '0.00') FROM debts WHERE status='active'");

        $invoicesThisMonth = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM invoices WHERE period = ? AND deleted_at IS NULL",
            [date('Y-m')]
        );

        // Monthly chart - last 6 months
        $monthlyChart = $conn->fetchAllAssociative(
            "SELECT period, 
                    COALESCE(SUM(CASE WHEN payment_method='fakt' THEN amount ELSE 0 END), 0) AS fakt_amount,
                    COALESCE(SUM(CASE WHEN payment_method='naqt' THEN amount ELSE 0 END), 0) AS naqt_amount
             FROM payments 
             WHERE period >= ? 
             GROUP BY period ORDER BY period",
            [(new \DateTimeImmutable('-6 months'))->format('Y-m')]
        );

        // By payment type
        $byPaymentType = $conn->fetchAssociative(
            "SELECT 
                SUM(payment_type='fakt') AS fakt,
                SUM(payment_type='naqt') AS naqt,
                SUM(payment_type='qarz') AS qarz
             FROM clients WHERE status='faol' AND deleted_at IS NULL"
        );

        // Debtors breakdown by payment_type_snapshot
        $debtorsBreakdown = $conn->fetchAssociative(
            "SELECT 
                COALESCE(SUM(payment_type_snapshot='fakt'), 0) AS fromFakt,
                COALESCE(SUM(payment_type_snapshot='naqt'), 0) AS fromNaqt,
                COALESCE(SUM(payment_type_snapshot='qarz'), 0) AS fromQarz
             FROM debts WHERE status='active'"
        );

        return [
            'activeClients' => $activeClients,
            'debtorsCount' => $debtorsCount,
            'totalDebt' => $totalDebt,
            'invoicesThisMonth' => $invoicesThisMonth,
            'monthlyChart' => $monthlyChart,
            'byPaymentType' => [
                'fakt' => (int) ($byPaymentType['fakt'] ?? 0),
                'naqt' => (int) ($byPaymentType['naqt'] ?? 0),
                'qarz' => (int) ($byPaymentType['qarz'] ?? 0),
            ],
            'debtorsBreakdown' => [
                'fromFakt' => (int) ($debtorsBreakdown['fromFakt'] ?? 0),
                'fromNaqt' => (int) ($debtorsBreakdown['fromNaqt'] ?? 0),
                'fromQarz' => (int) ($debtorsBreakdown['fromQarz'] ?? 0),
            ],
        ];
    }
}
