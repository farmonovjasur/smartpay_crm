<?php

declare(strict_types=1);

namespace App\Service\Ledger;

use App\Entity\LedgerEntry;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Qarzdaftari — Excel eksport.
 */
final class LedgerExporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function exportFiltered(string $status, string $search = ''): StreamedResponse
    {
        $rows = $this->getExportRows($status, $search);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Qarzdaftari');

        // Format columns
        $sheet->getStyle('C:C')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT); // INN
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT); // Phone
        $sheet->getStyle('F:F')->getNumberFormat()->setFormatCode('#,##0.00'); // Total
        $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode('#,##0.00'); // Paid
        $sheet->getStyle('H:H')->getNumberFormat()->setFormatCode('#,##0.00'); // Remaining

        // Header row
        $headers = [
            'A' => 'T/r',
            'B' => 'Mijoz nomi',
            'C' => 'INN',
            'D' => 'Telefon',
            'E' => 'Xizmatlar',
            'F' => 'Jami summa',
            'G' => 'To\'langan',
            'H' => 'Qoldiq',
            'I' => 'Holat',
            'J' => 'Yaratilgan sana',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
        }

        // Header style
        $sheet->getStyle('A1:J1')->applyFromArray([
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
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $row = 2;
        $index = 1;
        foreach ($rows as $r) {
            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('B' . $row, $r['client_name']);
            $sheet->setCellValueExplicit('C' . $row, $r['client_inn'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $row, $r['client_phone'], DataType::TYPE_STRING);
            $sheet->setCellValue('E' . $row, $r['services_text']);
            $sheet->setCellValue('F' . $row, (float) $r['total_amount']);
            $sheet->setCellValue('G' . $row, (float) $r['paid_amount']);
            $sheet->setCellValue('H' . $row, (float) $r['remaining_amount']);
            $sheet->setCellValue('I' . $row, $this->statusLabel($r['status']));
            $sheet->setCellValue('J' . $row, $r['created_at']);

            $row++;
            $index++;
        }

        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'qarzdaftari_' . date('Y-m-d') . '.xlsx';

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getExportRows(string $status, string $search): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e', 'c', 'i')
            ->from(LedgerEntry::class, 'e')
            ->innerJoin('e.client', 'c')
            ->leftJoin('e.items', 'i')
            ->orderBy('e.id', 'DESC');

        if ($status !== 'all') {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.inn LIKE :search OR c.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        /** @var LedgerEntry[] $entries */
        $entries = $qb->getQuery()->getResult();

        return array_map(function (LedgerEntry $e) {
            // Xizmat qatorlarini bitta matn sifatida birlashtirish
            $services = [];
            foreach ($e->getItems() as $item) {
                $services[] = $item->getDescription() . ' (' . number_format((float) $item->getAmount(), 0, '.', ' ') . ' so\'m)';
            }

            return [
                'client_name' => $e->getClient()->getName(),
                'client_inn' => $e->getClient()->getInn(),
                'client_phone' => $e->getClient()->getPhone(),
                'services_text' => implode('; ', $services),
                'total_amount' => $e->getTotalAmount(),
                'paid_amount' => $e->getPaidAmount(),
                'remaining_amount' => $e->getRemainingAmount(),
                'status' => $e->getStatus()->value,
                'created_at' => $e->getCreatedAt()->format('d.m.Y'),
            ];
        }, $entries);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Faol',
            'partial' => 'Qisman to\'langan',
            'paid' => 'To\'langan',
            default => $status,
        };
    }
}
