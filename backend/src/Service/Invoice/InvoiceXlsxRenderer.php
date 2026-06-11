<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\PaymentType;
use App\Service\Config\ConfigService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders an Invoice as the official O'zbekiston Soliq Qo'mitasi EHF XLSX (73 columns).
 *
 * The output exactly mirrors the official template structure:
 *   Rows 1–5: empty (height 6.75 — effectively hidden)
 *   Row 6: group headers with colored backgrounds & merged cells
 *   Row 7: sub-headers with colored backgrounds & merged cells
 *   Row 8: additional sub-headers (Aktsiz, NDS, Markirovka)
 *   Row 9: column numbers 1–73 with section colors
 *   Row 10+: data rows (one per fakt client)
 *
 * Only clients with payment_type = "fakt" are included.
 */
final class InvoiceXlsxRenderer
{
    // Section background colors for row 9 (column numbers)
    private const COLOR_GENERAL = 'FFFF00';   // Col 1-12 (Umumiy)
    private const COLOR_SELLER  = '92D050';   // Col 13-26 (Исполнитель)
    private const COLOR_BUYER   = '00B0F0';   // Col 27-40 (Заказчик)
    private const COLOR_GOODS   = 'FFC000';   // Col 41-73 (Товары)

    public function __construct(
        private readonly ConfigService $configService,
    ) {
    }

    public function render(Invoice $invoice): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();

        // Set default font
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('sheet1');

        // Load config values
        $sellerInn = $this->configService->get('seller_inn');
        $responsibleName = $this->configService->get('responsible_name');
        $ikpuCode = $this->configService->get('ikpu_code');
        $unitCode = $this->configService->get('unit_code');
        $taxBenefitCode = $this->configService->get('tax_benefit_code');
        $originCode = $this->configService->get('origin_code');

        // Invoice metadata
        $invoiceNumber = $invoice->getInvoiceNumber();
        $issueDate = $invoice->getIssueDate()->format('d.m.Y');
        $productName = $invoice->getProductNameSnapshot();

        // ─── Set column widths ───
        $this->setColumnWidths($sheet);

        // ─── Set row heights ───
        for ($r = 1; $r <= 5; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(6.75);
        }
        $sheet->getRowDimension(6)->setRowHeight(30.75);
        $sheet->getRowDimension(7)->setRowHeight(51.75);
        $sheet->getRowDimension(8)->setRowHeight(48);
        $sheet->getRowDimension(9)->setRowHeight(16.2);

        // ─── Build header rows (6, 7, 8, 9) ───
        $this->writeRow6($sheet);
        $this->writeRow7($sheet);
        $this->writeRow8($sheet);
        $this->writeRow9($sheet);
        $this->applyMergedCells($sheet);
        $this->applyHeaderStyles($sheet);

        // ─── Write data rows starting at row 10 ───
        $faktItems = $this->filterFaktItems($invoice);

        // Birlik narx — config'dan (talabga ko'ra barcha mijoz uchun bir xil: 1 dona = 100 000)
        $unitPriceInt = (int) round((float) $this->configService->get('unit_price'));

        $dataRow = 10;
        $index = 1;
        foreach ($faktItems as $item) {
            $client = $item->getClient();
            $quantity = $item->getQuantity();                         // Col52: mijozga qarab o'zgaradi
            $totalPrice = $unitPriceInt * $quantity;                  // Col56: umumiy narx = soni × narx

            $rowData = [
                1  => (string) $index,                               // п.п. — tartib raqami
                2  => '0',                                           // Тип СФ — doim 0
                // Col 4 (D): bo'sh — soliq tizimi tomonidan to'ldiriladi
                5  => $issueDate,                                    // SF sana — BARCHA uchun bir xil
                6  => 'Оммавий оферта',                              // Shartnoma nomi — BARCHA uchun bir xil
                7  => $client->getServiceDate()->format('d.m.Y'),    // Shartnoma sana — MIJOZGA qarab
                14 => $sellerInn,                                    // Seller INN — BARCHA uchun bir xil
                24 => $responsibleName,                              // Direktor — BARCHA uchun bir xil
                25 => $responsibleName,                              // Bosh hisobchi — BARCHA uchun bir xil
                28 => $item->getClientInnSnapshot(),                 // Mijoz INN — MIJOZGA qarab
                41 => '1',                                           // Tovar p.p. — BARCHA uchun bir xil
                45 => $productName,                                  // Tovar nomi — BARCHA uchun bir xil (oy/yil o'zgaradi)
                46 => $ikpuCode,                                     // IKPU — BARCHA uchun bir xil
                49 => $unitCode,                                     // O'lchov birligi — BARCHA uchun bir xil
                52 => (string) $quantity,                            // Soni — MIJOZGA qarab
                53 => (string) $unitPriceInt,                        // 1 dona narx — BARCHA uchun bir xil (100000)
                56 => (string) $totalPrice,                          // Umumiy narx — soni × narx
                57 => 'Без НДС',                                     // NDS — BARCHA uchun bir xil
                58 => '0',                                           // NDS summa — BARCHA uchun bir xil
                59 => (string) $totalPrice,                          // Jami = Col56 bilan bir xil
                60 => $taxBenefitCode,                               // Imtiyoz kodi — BARCHA uchun bir xil
                65 => $originCode,                                   // Kelib chiqishi — BARCHA uchun bir xil
            ];

            foreach ($rowData as $col => $value) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->getCell($colLetter . $dataRow)->setValueExplicit($value, DataType::TYPE_STRING);
            }

            // Style data row: center alignment, font Arial 10
            $lastCol = Coordinate::stringFromColumnIndex(73);
            $sheet->getStyle("A{$dataRow}:{$lastCol}{$dataRow}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'name' => 'Arial',
                    'size' => 10,
                    'bold' => false,
                ],
            ]);

            $dataRow++;
            $index++;
        }

        // ─── Set all cells to text format (@) ───
        $highestRow = $sheet->getHighestRow();
        $lastCol = Coordinate::stringFromColumnIndex(73);
        $sheet->getStyle("A1:{$lastCol}{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('@');

        $filename = $invoiceNumber . '.xlsx';

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * @return InvoiceItem[]
     */
    private function filterFaktItems(Invoice $invoice): array
    {
        $items = [];
        foreach ($invoice->getItems() as $item) {
            if ($item->getPaymentTypeSnapshot() === PaymentType::Fakt) {
                $items[] = $item;
            }
        }
        return $items;
    }

    private function setColumnWidths($sheet): void
    {
        $widths = [
            1 => 6.66, 2 => 7.44, 3 => 7.44, 4 => 16.66, 5 => 18.55,
            6 => 16.44, 7 => 13.66, 8 => 13.66, 9 => 6.33, 10 => 25.66,
            11 => 14.66, 12 => 33.89, 13 => 25.55, 14 => 14.66, 15 => 12.11,
            16 => 22.55, 17 => 7.11, 18 => 9.66, 19 => 20.66, 20 => 21.89,
            21 => 21.33, 22 => 9.66, 23 => 5.55, 24 => 19.0, 25 => 19.55,
            26 => 13.89, 27 => 9.11, 28 => 16.44, 29 => 7.55, 30 => 4.11,
            31 => 3.11, 32 => 3.66, 33 => 1.55, 34 => 18.89, 35 => 6.0,
            36 => 9.66, 37 => 2.44, 38 => 20.66, 39 => 20.55, 40 => 25.11,
            41 => 9.66, 42 => 0.44, 43 => 18.66, 44 => 9.33, 45 => 40.44,
            46 => 38.11, 47 => 13.89, 48 => 8.44, 49 => 20.66, 50 => 8.33,
            51 => 9.0, 52 => 10.44, 53 => 11.55, 54 => 7.11, 55 => 2.44,
            56 => 15.11, 57 => 14.55, 58 => 9.11, 59 => 16.55, 60 => 16.0,
            61 => 8.0, 62 => 10.0, 63 => 10.11, 64 => 6.0, 65 => 8.44,
            66 => 16.55, 67 => 18.0, 68 => 15.11, 69 => 16.11, 70 => 9.11,
            71 => 9.11, 72 => 44.44, 73 => 26.89,
        ];

        foreach ($widths as $col => $width) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setWidth($width);
        }
    }

    private function writeRow6($sheet): void
    {
        $values = [
            1  => 'п.п.',
            2  => 'Тип счета-фактуры / Ҳисоб-фактура тури',
            3  => 'Код типа одностороннего счета-фактуры / Бир томонлама ҳисоб-фактура тури коди',
            4  => 'Счет-фактура / Ҳисоб-фактура',
            6  => 'Договор / Шартнома',
            8  => 'Доверенность / Ишончнома',
            12 => 'Товар отпустил (ИНН/ПИНФЛ) / Маҳсулот чиқарди (СТИР/ЖШШР)',
            13 => 'Исполнитель / Бажарувчи',
            27 => 'Заказчик / Буюртмачи',
            41 => 'Товары (услуги) / Маҳсулотлар (хизматлар)',
            67 => 'Данные по ID договора аренды / Ижара шартномаси ID бўйича маълумотлар',
        ];
        foreach ($values as $col => $val) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getCell($colLetter . '6')->setValue($val);
        }
    }

    private function writeRow7($sheet): void
    {
        $values = [
            4  => '№',
            5  => 'Дата / Сана',
            6  => '№',
            7  => 'Дата / Сана',
            8  => '№',
            9  => 'Дата / Сана',
            10 => 'ФИО / ФИШ',
            11 => 'ИНН / СТИР',
            13 => 'Наименование / Номланиши',
            14 => 'ИНН / СТИР',
            15 => 'Код филиала / Филиал коди',
            16 => 'Название филиала / Филиал номи',
            17 => 'Расчетный счет / Ҳисоб рақами',
            18 => 'МФО',
            19 => 'Адрес / Манзил',
            20 => 'Телефон / Телефон рақами',
            21 => 'Мобильный / Мобил рақами',
            22 => 'ОКЭД',
            23 => 'Район (код) / Туман (код)',
            24 => 'Директор',
            25 => 'Гл.бухгалтер / Бош ҳисобчи',
            26 => 'Код плательщика НДС / ҚҚС тўловчи коди',
            27 => 'Наименование / Номланиши',
            28 => 'ИНН / СТИР',
            29 => 'Код филиала / Филиал коди',
            30 => 'Название филиала / Филиал номи',
            31 => 'Расчетный счет / Ҳисоб рақами',
            32 => 'МФО',
            33 => 'Адрес / Манзил',
            34 => 'Телефон / Телефон рақами',
            35 => 'Мобильный / Мобил рақами',
            36 => 'ОКЭД',
            37 => 'Район (код) / Туман (код)',
            38 => 'Директор',
            39 => 'Гл.бухгалтер / Бош ҳисобчи',
            40 => 'Код плательщика НДС / ҚҚС тўловчи коди',
            41 => 'п.п.',
            42 => 'Наименование комитента / Комитент номи',
            43 => 'ИНН комитента / Комитент СТИРи',
            44 => 'Рег. код платель. НДС комитента / Комитентнинг ҚҚС тўловчи рўйхатдан ўтиш коди',
            45 => 'Наименование / Номланиши',
            46 => 'ИКПУ и наименование товаров / МХИК ва маҳсулот номи',
            47 => 'Штрих код товара/услуги / Маҳсулот/хизматнинг штрих-коди',
            48 => 'Серия товара / Товар серияси',
            49 => 'Ед. изм. (код) / Ўлчов бирлиги. (код)',
            50 => 'Базовая цена / Асосий нарх',
            51 => '% добавочной стоимости / Қўшимча нарх, %',
            52 => 'Кол-во / Сони',
            53 => 'Цена / Нарх',
            54 => 'Акцизный налог / Акциз солиғи',
            56 => 'Стоимость поставки / Етказиб бериш нархи',
            57 => 'НДС / ҚҚС',
            59 => 'Стоимость поставки с учетом НДС / ҚҚСни ҳисобга олган ҳолда етказиб бериш нархи',
            60 => 'Код Льготы / Имтиёз коди',
            61 => 'Маркировки / Маркировкалар',
            64 => 'ID Склада / Омбор ID си',
            65 => 'Происхождение товара / Товарни келиб чиқиши',
            66 => 'ID договора / Шартнома ID си',
            67 => 'ID договора / Шартнома ID си',
            68 => 'Период СФ От / ҲФ давридан',
            69 => 'Период СФ До / ҲФ даврига қадар',
            70 => 'Лот ID / Лот ID си',
            71 => 'ТТН ID / ТТН ID си',
            72 => 'ID входящей СФ (для СФ на возмещение) / Кирувчи ҲФ ID си (харажатларни қоплаш учун ҲФ)',
            73 => 'Отпуск лекарственных средств (для ФАРМ) / Дори воситаларини бериш усули (ФАРМ учун)',
        ];
        foreach ($values as $col => $val) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getCell($colLetter . '7')->setValue($val);
        }
    }

    private function writeRow8($sheet): void
    {
        $values = [
            54 => 'Ставка / Миқдор',
            55 => 'Сумма / Миқдор',
            57 => 'Ставка',
            58 => 'Сумма / Йиғинди',
            61 => 'Тип товара / Товар тури',
            62 => 'Тип упаковки / Қоплама тури',
            63 => 'Коды маркировок (через пробел) / Маркировка кодлари (бўшлиқ билан)',
        ];
        foreach ($values as $col => $val) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getCell($colLetter . '8')->setValue($val);
        }
    }

    private function writeRow9($sheet): void
    {
        for ($col = 1; $col <= 73; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getCell($colLetter . '9')->setValueExplicit((string) $col, DataType::TYPE_STRING);
        }
    }

    private function applyMergedCells($sheet): void
    {
        $merges = [
            // Row 6 group merges
            'A6:A8',    // п.п. (vertical)
            'B6:B8',    // Тип СФ (vertical)
            'C6:C8',    // Код типа (vertical)
            'D6:E6',    // Счет-фактура
            'F6:G6',    // Договор
            'H6:K6',    // Доверенность
            'L6:L8',    // Товар отпустил (vertical)
            'M6:Z6',    // Исполнитель
            'AA6:AN6',  // Заказчик
            'AO6:BN6',  // Товары
            'BO6:BQ6',  // Данные по ID договора аренды

            // Row 7 sub-header merges (vertical 7-8)
            'D7:D8', 'E7:E8', 'F7:F8', 'G7:G8',
            'H7:H8', 'I7:I8', 'J7:J8', 'K7:K8',
            'M7:M8', 'N7:N8', 'O7:O8', 'P7:P8',
            'Q7:Q8', 'R7:R8', 'S7:S8', 'T7:T8',
            'U7:U8', 'V7:V8', 'W7:W8', 'X7:X8',
            'Y7:Y8', 'Z7:Z8',
            'AA7:AA8', 'AB7:AB8', 'AC7:AC8', 'AD7:AD8',
            'AE7:AE8', 'AF7:AF8', 'AG7:AG8', 'AH7:AH8',
            'AI7:AI8', 'AJ7:AJ8', 'AK7:AK8', 'AL7:AL8',
            'AM7:AM8', 'AN7:AN8',
            'AO7:AO8', 'AP7:AP8', 'AQ7:AQ8', 'AR7:AR8',
            'AS7:AS8', 'AT7:AT8', 'AU7:AU8', 'AV7:AV8',
            'AW7:AW8', 'AX7:AX8', 'AY7:AY8', 'AZ7:AZ8',
            'BA7:BA8',
            'BB7:BC7',  // Акцизный налог (horizontal)
            'BD7:BD8',
            'BE7:BF7',  // НДС (horizontal)
            'BG7:BG8', 'BH7:BH8',
            'BI7:BK7',  // Маркировки (horizontal)
            'BL7:BL8', 'BM7:BM8', 'BN7:BN8',
            'BO7:BO8', 'BP7:BP8', 'BQ7:BQ8',
            'BR7:BR8', 'BS7:BS8', 'BT7:BT8', 'BU7:BU8',
        ];

        foreach ($merges as $range) {
            $sheet->mergeCells($range);
        }
    }

    private function applyHeaderStyles($sheet): void
    {
        // ─── Row 6 colors ───
        // Colors map: col ranges => background color
        $row6Colors = [
            [1, 2, 'FF0000'],    // п.п., Тип СФ
            [3, 3, 'FFFF00'],    // Код типа
            [4, 7, 'FF0000'],    // Счет-фактура, Договор
            [8, 11, 'FFFF00'],   // Доверенность
            [12, 12, 'D7E4BD'],  // Товар отпустил
            [13, 26, '92D050'],  // Исполнитель
            [27, 40, '00B0F0'],  // Заказчик
            [41, 66, 'FFC000'],  // Товары
            [67, 69, '92D050'],  // Данные по ID договора аренды
            [70, 73, 'FFC000'],  // Remaining goods section
        ];
        $this->applyRowColors($sheet, 6, $row6Colors);

        // ─── Row 7 colors (complex pattern per column) ───
        $row7Colors = [
            [1, 2, 'FF0000'],
            [3, 3, 'FFFF00'],
            [4, 7, 'FF0000'],
            [8, 11, 'FFFF00'],
            [12, 12, 'D7E4BD'],
            [13, 13, 'FFFF00'],
            [14, 14, 'FF0000'],
            [15, 23, 'FFFF00'],
            [24, 25, 'FF0000'],
            [26, 27, 'FFFF00'],
            [28, 28, 'FF0000'],
            [29, 40, 'FFFF00'],
            [41, 41, 'FF0000'],
            [42, 44, 'FFFF00'],
            [45, 46, 'FF0000'],
            [47, 47, '4F81BD'],
            [48, 48, 'FFFF00'],
            [49, 49, 'FF0000'],
            [50, 52, 'FFFF00'],
            [53, 53, 'FF0000'],
            [54, 55, 'FFFF00'],
            [56, 56, 'FF0000'],
            [57, 59, 'FF0000'],
            [60, 64, 'FFFF00'],
            [65, 65, 'FF0000'],
            [66, 72, 'FFFF00'],
            [73, 73, '92D050'],
        ];
        $this->applyRowColors($sheet, 7, $row7Colors);

        // ─── Row 8 colors (same as row 7 continuation) ───
        $this->applyRowColors($sheet, 8, $row7Colors);

        // ─── Row 9 colors (section-based) ───
        $row9Colors = [
            [1, 12, self::COLOR_GENERAL],
            [13, 26, self::COLOR_SELLER],
            [27, 40, self::COLOR_BUYER],
            [41, 73, self::COLOR_GOODS],
        ];
        $this->applyRowColors($sheet, 9, $row9Colors);

        // ─── Font styling for rows 6–9: bold, size 12, center ───
        $lastCol = Coordinate::stringFromColumnIndex(73);
        $headerStyle = [
            'font' => [
                'name' => 'Arial',
                'size' => 12,
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
        $sheet->getStyle("A6:{$lastCol}9")->applyFromArray($headerStyle);

        // ─── Thin borders for header area ───
        $sheet->getStyle("A6:{$lastCol}9")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);
    }

    /**
     * Apply background colors to a row by column ranges.
     * @param array<int, array{0: int, 1: int, 2: string}> $colorRanges [startCol, endCol, hexColor]
     */
    private function applyRowColors($sheet, int $row, array $colorRanges): void
    {
        foreach ($colorRanges as [$startCol, $endCol, $color]) {
            $startLetter = Coordinate::stringFromColumnIndex($startCol);
            $endLetter = Coordinate::stringFromColumnIndex($endCol);
            $sheet->getStyle("{$startLetter}{$row}:{$endLetter}{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $color],
                ],
            ]);
        }
    }
}
