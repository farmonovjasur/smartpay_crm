<?php

declare(strict_types=1);

namespace App\Service\Client;

use App\Dto\Client\ClientFilter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ClientExporter
{
    public function __construct(
        private readonly ClientService $clientService,
    ) {
    }

    /**
     * Export filtered clients to Excel matching the template layout:
     *
     * A = INN, C = Mijoz nomi, E = Tel raqam, G = Ulangan sana,
     * I = To'lov turi, K = Maxsulot soni, M = Oxirgi to'lov
     *
     * Header on row 3, data starts on row 4.
     */
    public function exportFiltered(ClientFilter $f): StreamedResponse
    {
        // Get all results (no pagination limit)
        $f->pageSize = 10000;
        $f->page = 1;
        $result = $this->clientService->findPaginated($f);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mijozlar');

        // ── Text-format ustunlar (raqam → ilmiy notatsiya bo'lmasligi uchun) ──
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('M:M')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('O:O')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // ── Header row (row 3) ────────────────────────────────────────────────
        $headerCols = ['A', 'C', 'E', 'G', 'I', 'K', 'M', 'O'];
        $headerLabels = [
            'INN',
            'Mijoz nomi',
            'Tel raqam',
            'Ulangan sana',
            "To'lov turi",
            'Maxsulot soni',
            "Oxirgi to'lov",
            "Qo'sh. tel",
        ];

        foreach ($headerCols as $idx => $col) {
            $sheet->setCellValue($col . '3', $headerLabels[$idx]);
        }

        // Style header row: dark grey fill, white bold text, centered
        $sheet->getStyle('A3:P3')->applyFromArray([
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

        // ── Data rows (starting from row 4) ───────────────────────────────────
        $row = 4;
        foreach ($result['items'] as $client) {
            $sheet->setCellValueExplicit('A' . $row, $client->getInn(), DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $row, $client->getName());
            $sheet->setCellValueExplicit('E' . $row, $client->getPhone(), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('G' . $row, $client->getServiceDate()->format('Y-m-d'), DataType::TYPE_STRING);
            $sheet->setCellValue('I' . $row, $client->getPaymentType()->value);
            $sheet->setCellValue('K' . $row, $client->getProductCount());
            $lastPaid = $client->getLastPaidPeriod();
            $sheet->setCellValueExplicit('M' . $row, $lastPaid ?? '', DataType::TYPE_STRING);
            $phone2 = $client->getPhone2();
            $sheet->setCellValueExplicit('O' . $row, $phone2 ?? '', DataType::TYPE_STRING);
            $row++;
        }

        // ── Column widths ─────────────────────────────────────────────────────
        $colWidths = [
            'A' => 18,  // INN
            'B' => 4,
            'C' => 30,  // Mijoz nomi
            'D' => 4,
            'E' => 18,  // Tel raqam
            'F' => 4,
            'G' => 16,  // Ulangan sana
            'H' => 4,
            'I' => 14,  // To'lov turi
            'J' => 4,
            'K' => 14,  // Maxsulot soni
            'L' => 4,
            'M' => 16,  // Oxirgi to'lov
            'N' => 4,
            'O' => 18,  // Qo'sh. tel
            'P' => 4,
        ];

        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getRowDimension(3)->setRowHeight(22);
        $sheet->freezePane('A4');

        // ── Build response ────────────────────────────────────────────────────
        $filename = 'mijozlar_' . date('Y-m-d_H-i') . '.xlsx';

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
