<?php

declare(strict_types=1);

namespace App\Service\Client;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TemplateGenerator
{
    /**
     * Column layout (every other column is used, matching the example Excel):
     *
     * A3  = INN
     * C3  = Mijoz nomi
     * E3  = Tel raqam
     * G3  = Ulangan sana
     * I3  = To'lov turi
     * K3  = Maxsulot soni
     * M3  = Oxirgi to'lov
     *
     * Data starts at row 4.
     */
    public function generate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mijozlar');

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
            $cell = $col . '3';
            $sheet->setCellValue($cell, $headerLabels[$idx]);
        }

        // Style header row: dark grey fill, white bold text, centered
        $headerRange = 'A3:P3';
        $sheet->getStyle($headerRange)->applyFromArray([
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

        // ── INN (A), Tel raqam (E), Ulangan sana (G), Oxirgi to'lov (M) ────────
        // ustunlarini matn formatiga o'tkazish — Excel raqam/sana sifatida
        // o'zgartirmasligi uchun.
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('M:M')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('O:O')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // ── Sample data row (row 4) ───────────────────────────────────────────
        // INN va Tel raqamni setCellValueExplicit bilan matn sifatida yozamiz
        $sheet->setCellValueExplicit('A4', '123456789', DataType::TYPE_STRING);
        $sheet->setCellValue('C4', 'Namuna Kompaniya');
        $sheet->setCellValueExplicit('E4', '+998901234567', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('G4', '2025-01-15', DataType::TYPE_STRING);
        $sheet->setCellValue('I4', 'fakt');
        $sheet->setCellValue('K4', '2');
        $sheet->setCellValueExplicit('M4', '2025-01', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('O4', '+998901112233', DataType::TYPE_STRING);

        // Style sample row: italic blue text
        $sheet->getStyle('A4:P4')->applyFromArray([
            'font' => [
                'italic' => true,
                'color'  => ['rgb' => '0000FF'],
            ],
        ]);

        // ── Column widths ─────────────────────────────────────────────────────
        $colWidths = [
            'A' => 18,  // INN
            'B' => 4,   // spacer
            'C' => 30,  // Mijoz nomi
            'D' => 4,   // spacer
            'E' => 18,  // Tel raqam
            'F' => 4,   // spacer
            'G' => 16,  // Ulangan sana
            'H' => 4,   // spacer
            'I' => 14,  // To'lov turi
            'J' => 4,   // spacer
            'K' => 14,  // Maxsulot soni
            'L' => 4,   // spacer
            'M' => 16,  // Oxirgi to'lov
            'N' => 4,   // spacer
            'O' => 18,  // Qo'sh. tel
            'P' => 4,   // spacer
        ];

        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Row heights
        $sheet->getRowDimension(3)->setRowHeight(22);
        $sheet->getRowDimension(4)->setRowHeight(18);

        // ── Freeze panes below header ─────────────────────────────────────────
        $sheet->freezePane('A4');

        // ── Build response ────────────────────────────────────────────────────
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="mijozlar_shablon.xlsx"');

        return $response;
    }
}
