<?php

declare(strict_types=1);

namespace App\Service\Debt;

use App\Entity\Debt;
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
    ) {
    }

    public function exportFiltered(string $status): StreamedResponse
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

        $debts = $qb->getQuery()->getResult();

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
        /** @var Debt $debt */
        foreach ($debts as $debt) {
            $client = $debt->getClient();
            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('C' . $row, $client->getName());
            $sheet->setCellValueExplicit('E' . $row, $client->getInn(), DataType::TYPE_STRING);
            $sheet->setCellValue('G' . $row, $client->getProductCount());
            $sheet->setCellValue('I' . $row, $debt->getMonthsOverdue() . ' oy');
            $sheet->setCellValue('K' . $row, (float) $debt->getAmount());
            $sheet->setCellValueExplicit('M' . $row, $client->getPhone(), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('O' . $row, $client->getPhone2() ?? '', DataType::TYPE_STRING);
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
}
