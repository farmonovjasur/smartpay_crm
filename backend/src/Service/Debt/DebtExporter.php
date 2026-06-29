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

        // Format columns as text where needed (INN, Phone)
        $sheet->getStyle('C:C')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('K:K')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        // Format Amount as number
        $sheet->getStyle('I:I')->getNumberFormat()->setFormatCode('#,##0.00');

        // Header row
        $headerCols = ['A', 'C', 'E', 'G', 'I', 'K'];
        $headerLabels = [
            'Mijoz nomi',
            'INN',
            'Olingan maxsulot soni',
            'Qarz muddati',
            'Qarz summasi',
            'Tel raqami',
        ];

        foreach ($headerCols as $idx => $col) {
            $sheet->setCellValue($col . '3', $headerLabels[$idx]);
        }

        $sheet->getStyle('A3:K3')->applyFromArray([
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
        /** @var Debt $debt */
        foreach ($debts as $debt) {
            $client = $debt->getClient();
            $sheet->setCellValue('A' . $row, $client->getName());
            $sheet->setCellValueExplicit('C' . $row, $client->getInn(), DataType::TYPE_STRING);
            $sheet->setCellValue('E' . $row, $client->getProductCount());
            $sheet->setCellValue('G' . $row, $debt->getDueDate()->format('Y-m-d'));
            $sheet->setCellValue('I' . $row, (float) $debt->getAmount());
            $sheet->setCellValueExplicit('K' . $row, $client->getPhone(), DataType::TYPE_STRING);
            $row++;
        }

        $colWidths = [
            'A' => 30, // Mijoz nomi
            'B' => 4,
            'C' => 18, // INN
            'D' => 4,
            'E' => 24, // Maxsulot soni
            'F' => 4,
            'G' => 16, // Qarz muddati
            'H' => 4,
            'I' => 18, // Qarz summasi
            'J' => 4,
            'K' => 18, // Tel raqami
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
