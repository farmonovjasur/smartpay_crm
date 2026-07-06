<?php

declare(strict_types=1);

namespace App\Service\Client;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentHistoryExporter
{
    public function __construct(
        private readonly ClientService $clientService,
    ) {
    }

    public function export(int $clientId): StreamedResponse
    {
        $client = $this->clientService->findById($clientId);
        $history = $this->clientService->getPaymentHistory($clientId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('To\'lovlar tarixi');

        $sheet->setCellValue('A1', 'Mijoz: ' . $client->getName());
        $sheet->setCellValue('A2', 'INN: ' . $client->getInn());
        $sheet->setCellValue('A3', 'Balans: ' . number_format((float) $client->getBalance(), 2, '.', ' ') . ' so\'m');
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);

        $headerCols = ['A', 'B', 'C', 'D', 'E', 'F'];
        $headerLabels = [
            'Sana',
            'Davr',
            'Summa (so\'m)',
            'To\'lov turi',
            'To\'lov usuli',
            'Kiritgan xodim',
        ];

        foreach ($headerCols as $idx => $col) {
            $sheet->setCellValue($col . '5', $headerLabels[$idx]);
        }

        $sheet->getStyle('A5:F5')->applyFromArray([
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

        $row = 6;
        foreach ($history as $payment) {
            $date = (new \DateTimeImmutable($payment['paid_at']))->format('Y-m-d H:i');
            $sheet->setCellValueExplicit('A' . $row, $date, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $row, $payment['period'], DataType::TYPE_STRING);
            
            $amount = (float) $payment['amount'];
            $sheet->setCellValueExplicit('C' . $row, number_format($amount, 2, '.', ' '), DataType::TYPE_STRING);
            
            $type = $payment['is_debt'] ? 'Qarz to\'lovi' : 'Oylik to\'lov';
            $sheet->setCellValue('D' . $row, $type);
            
            $method = $payment['method'] === 'fakt' ? 'Fakt' : 'Naqt';
            $sheet->setCellValue('E' . $row, $method);
            
            $createdBy = $payment['created_by'] ?? 'Tizim';
            $sheet->setCellValue('F' . $row, $createdBy);
            $row++;
        }

        $colWidths = [
            'A' => 20,
            'B' => 12,
            'C' => 18,
            'D' => 20,
            'E' => 15,
            'F' => 25,
        ];

        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getRowDimension(5)->setRowHeight(22);
        $sheet->freezePane('A6');

        $filename = 'tolovlar_tarixi_' . $client->getInn() . '_' . date('Y-m-d_H-i') . '.xlsx';

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
