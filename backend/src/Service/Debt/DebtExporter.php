<?php

declare(strict_types=1);

namespace App\Service\Debt;

use App\Entity\Client;
use App\Entity\Debt;
use App\Enum\ClientStatus;
use App\Enum\DebtStatus;
use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DebtExporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConfigService $configService,
    ) {
    }

    public function exportFiltered(string $status, string $search = ''): StreamedResponse
    {
        $rows = $this->getExportRows($status, $search);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Qarzdorlar');

        // Format columns as text where needed (INN, Phone, Phone2)
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('M:M')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('O:O')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        // Format Amount as number
        $sheet->getStyle('K:K')->getNumberFormat()->setFormatCode('#,##0.00');

        // Header row
        $headerCols = ['A', 'C', 'E', 'G', 'I', 'K', 'M', 'O'];
        $headerLabels = [
            'T/r',
            'Mijoz nomi',
            'INN',
            'Olingan maxsulot soni',
            'Qarz muddati',
            'Qarz summasi',
            'Tel raqami',
            "Qo'shimcha raqam",
        ];

        foreach ($headerCols as $idx => $col) {
            $sheet->setCellValue($col . '3', $headerLabels[$idx]);
        }

        $sheet->getStyle('A3:O3')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '595959'],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $row = 4;
        $index = 1;
        foreach ($rows as $r) {
            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('C' . $row, $r['name']);
            $sheet->setCellValueExplicit('E' . $row, $r['inn'], DataType::TYPE_STRING);
            $sheet->setCellValue('G' . $row, $r['product_count']);
            $sheet->setCellValue('I' . $row, $r['months_overdue'] . ' oy');
            $sheet->setCellValue('K' . $row, (float) $r['amount']);
            $sheet->setCellValueExplicit('M' . $row, $r['phone'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('O' . $row, $r['phone2'] ?? '', DataType::TYPE_STRING);
            $row++;
            $index++;
        }

        $colWidths = [
            'A' => 6,  // T/r
            'B' => 4,
            'C' => 30, // Mijoz nomi
            'D' => 4,
            'E' => 18, // INN
            'F' => 4,
            'G' => 24, // Maxsulot soni
            'H' => 4,
            'I' => 16, // Qarz muddati
            'J' => 4,
            'K' => 18, // Qarz summasi
            'L' => 4,
            'M' => 18, // Tel raqami
            'N' => 4,
            'O' => 18, // Qo'shimcha raqam
        ];

        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getRowDimension(3)->setRowHeight(22);
        $sheet->freezePane('A4');

        $filename = 'qarzdorlar_' . date('Y-m-d_H-i') . '.xlsx';

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Build export rows using clients as source of truth for active debtors,
     * and debts table for paid/historical records.
     *
     * @return array<array<string, mixed>>
     */
    private function getExportRows(string $status, string $search): array
    {
        // For "paid" status — use debts table (historical records)
        if ($status === 'paid') {
            return $this->getRowsFromDebtsTable($status, $search);
        }

        // For "active" or "all" — use clients as source of truth
        $currentPeriod = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))->format('Y-m');
        $unitPrice = $this->configService->get('unit_price');

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Client::class, 'c')
            ->where('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.lastPaidPeriod IS NOT NULL')
            ->andWhere('c.lastPaidPeriod < :currentPeriod')
            ->setParameter('status', ClientStatus::Faol)
            ->setParameter('currentPeriod', $currentPeriod)
            ->orderBy('c.id', 'DESC');

        if ($search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.inn LIKE :search OR c.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $clients = $qb->getQuery()->getResult();

        // Batch-load existing debts
        $clientIds = array_map(fn (Client $c) => $c->getId(), $clients);
        $debtsMap = $this->loadActiveDebtsMap($clientIds);

        $rows = [];
        foreach ($clients as $client) {
            /** @var Client $client */
            $debt = $debtsMap[$client->getId()] ?? null;

            if ($debt !== null) {
                $rows[] = [
                    'name' => $client->getName(),
                    'inn' => $client->getInn(),
                    'product_count' => $client->getProductCount(),
                    'months_overdue' => $debt->getMonthsOverdue(),
                    'amount' => $debt->getAmount(),
                    'phone' => $client->getPhone(),
                    'phone2' => $client->getPhone2(),
                ];
            } else {
                // Compute dynamically
                $lastPaid = $client->getLastPaidPeriod();
                $firstOverdue = $this->nextPeriod($lastPaid);
                $monthsOverdue = $this->countMonthsBetween($firstOverdue, $currentPeriod);
                $monthlyAmount = bcmul($unitPrice, (string) $client->getProductCount(), 2);
                $totalAmount = bcmul($monthlyAmount, (string) $monthsOverdue, 2);

                $rows[] = [
                    'name' => $client->getName(),
                    'inn' => $client->getInn(),
                    'product_count' => $client->getProductCount(),
                    'months_overdue' => $monthsOverdue,
                    'amount' => $totalAmount,
                    'phone' => $client->getPhone(),
                    'phone2' => $client->getPhone2(),
                ];
            }
        }

        // For "all" status, also append paid debts
        if ($status === 'all') {
            $paidRows = $this->getRowsFromDebtsTable('paid', $search);
            $rows = array_merge($rows, $paidRows);
        }

        return $rows;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getRowsFromDebtsTable(string $status, string $search): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('d')
            ->from(Debt::class, 'd')
            ->innerJoin('d.client', 'c')
            ->orderBy('d.id', 'DESC');

        if ($status !== 'all') {
            $qb->andWhere('d.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.inn LIKE :search OR c.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $debts = $qb->getQuery()->getResult();

        return array_map(fn (Debt $d) => [
            'name' => $d->getClient()->getName(),
            'inn' => $d->getClient()->getInn(),
            'product_count' => $d->getClient()->getProductCount(),
            'months_overdue' => $d->getMonthsOverdue(),
            'amount' => $d->getAmount(),
            'phone' => $d->getClient()->getPhone(),
            'phone2' => $d->getClient()->getPhone2(),
        ], $debts);
    }

    /**
     * @param int[] $clientIds
     * @return array<int, Debt>
     */
    private function loadActiveDebtsMap(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $debts = $this->em->createQueryBuilder()
            ->select('d')
            ->from(Debt::class, 'd')
            ->where('d.client IN (:ids)')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('ids', $clientIds)
            ->setParameter('statuses', [DebtStatus::Active, DebtStatus::Partial])
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($debts as $debt) {
            $map[$debt->getClient()->getId()] = $debt;
        }

        return $map;
    }

    private function nextPeriod(string $period): string
    {
        return (\DateTimeImmutable::createFromFormat('Y-m-d', $period . '-01'))
            ->modify('+1 month')
            ->format('Y-m');
    }

    private function countMonthsBetween(string $from, string $to): int
    {
        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $from . '-01');
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', $to . '-01');
        $diff = $start->diff($end);

        return max(1, ($diff->y * 12) + $diff->m + 1);
    }
}
