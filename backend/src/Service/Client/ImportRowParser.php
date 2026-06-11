<?php

declare(strict_types=1);

namespace App\Service\Client;

use App\Enum\PaymentType;
use App\Service\Util\PhoneNormalizer;

final class ImportRowParser
{
    /**
     * Parse a single Excel row into validated fields.
     *
     * Column layout (0-indexed, every other column used):
     *   0  (A) = INN
     *   2  (C) = Mijoz nomi
     *   4  (E) = Tel raqam
     *   6  (G) = Ulangan sana  (YYYY-MM-DD)
     *   8  (I) = To'lov turi   (fakt|naqt|qarz)
     *  10  (K) = Maxsulot soni
     *  12  (M) = Oxirgi to'lov (YYYY-MM, optional)
     *
     * @param array $row 0-indexed array from PhpSpreadsheet toArray()
     * @return array{valid: bool, data: array, errors: string[]}
     */
    public function parse(array $row): array
    {
        $errors = [];

        $inn          = preg_replace('/\D/', '', trim((string) ($row[0]  ?? '')));
        $name         = trim((string) ($row[2]  ?? ''));
        $phone        = trim((string) ($row[4]  ?? ''));
        $serviceDate  = trim((string) ($row[6]  ?? ''));
        $paymentType  = trim(mb_strtolower((string) ($row[8]  ?? '')));
        $productCount = trim((string) ($row[10] ?? ''));
        $lastPaid     = trim((string) ($row[12] ?? ''));
        $phone2       = trim((string) ($row[14] ?? ''));

        // ── Sana normalizatsiyasi ─────────────────────────────────────────────
        // Excel turli formatlarda sana saqlashi mumkin:
        //   YYYY-MM-DD       → to'g'ridan-to'g'ri qabul qilinadi
        //   D/M/YYYY         → konvertatsiya (04/05/2026 yoki 4/5/2026)
        //   DD.MM.YYYY       → konvertatsiya
        //   Excel serial num → DateHelper orqali
        $normalizedDate = null;
        if ($serviceDate !== '') {
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $serviceDate, $m)) {
                // YYYY-MM-DD yoki YYYY-M-D
                $normalizedDate = sprintf('%s-%02d-%02d', $m[1], (int) $m[2], (int) $m[3]);
            } elseif (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $serviceDate, $m)) {
                // D/M/YYYY, DD/MM/YYYY, DD.MM.YYYY, DD-MM-YYYY va hokazo
                $part1 = (int) $m[1];
                $part2 = (int) $m[2];
                $year  = (int) $m[3];

                // Avval DD/MM/YYYY deb sinab ko'ramiz
                if (checkdate($part2, $part1, $year)) {
                    $normalizedDate = sprintf('%04d-%02d-%02d', $year, $part2, $part1);
                } elseif (checkdate($part1, $part2, $year)) {
                    // MM/DD/YYYY
                    $normalizedDate = sprintf('%04d-%02d-%02d', $year, $part1, $part2);
                }
            } elseif (is_numeric($serviceDate)) {
                // Excel serial number
                try {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $serviceDate);
                    $normalizedDate = $dt->format('Y-m-d');
                } catch (\Throwable) {
                    $normalizedDate = null;
                }
            }
        }

        // ── payment_type normalizatsiyasi ─────────────────────────────────────
        // Keng tarqalgan imlo xatolarini tuzatamiz
        $paymentTypeAliases = [
            'naxt'   => 'naqt',
            'naqd'   => 'naqt',
            'nakd'   => 'naqt',
            'nakт'   => 'naqt',
            'fact'   => 'fakt',
            'faqt'   => 'fakt',
            'debt'   => 'qarz',
            'qarzi'  => 'qarz',
        ];
        $paymentType = $paymentTypeAliases[$paymentType] ?? $paymentType;

        // ── Validatsiya ───────────────────────────────────────────────────────

        // Validate service_date
        if ($normalizedDate === null) {
            $errors[] = "service_date: noto'g'ri format (YYYY-MM-DD, DD/MM/YYYY yoki MM/DD/YYYY kutiladi)";
        }

        // Validate INN
        if (!preg_match('/^\d{9}$|^\d{14}$/', $inn)) {
            $errors[] = 'inn: 9 yoki 14 ta raqam bo\'lishi kerak';
        }

        // Validate name
        if ($name === '') {
            $errors[] = 'name: bo\'sh bo\'lishi mumkin emas';
        }

        // Normalize phone
        $normalizedPhone = PhoneNormalizer::normalize($phone);
        if ($normalizedPhone === null) {
            $errors[] = 'phone: +998XXXXXXXXX formatiga keltirish imkonsiz';
        }

        // Normalize phone2 (optional)
        $normalizedPhone2 = null;
        if ($phone2 !== '') {
            $normalizedPhone2 = PhoneNormalizer::normalize($phone2);
            if ($normalizedPhone2 === null) {
                $errors[] = "phone2: +998XXXXXXXXX formatiga keltirish imkonsiz";
            }
        }

        // Validate payment_type
        $validTypes = array_map(fn ($t) => $t->value, PaymentType::cases());
        if (!in_array($paymentType, $validTypes, true)) {
            $errors[] = 'payment_type: fakt, naqt yoki qarz bo\'lishi kerak';
        }

        // Validate product_count
        $productCountInt = (int) $productCount;
        if ($productCountInt < 1) {
            $errors[] = 'product_count: musbat son bo\'lishi kerak';
        }

        // Validate last_paid_period (optional — YYYY-MM format)
        // Excel formatlangan holda turli ko'rinishda qaytarishi mumkin
        $lastPaidNorm = null;
        if ($lastPaid !== '') {
            if (preg_match('/^(\d{4})-(\d{1,2})(-\d{1,2})?$/', $lastPaid, $lm)) {
                // YYYY-MM yoki YYYY-MM-DD yoki YYYY-M → faqat YYYY-MM ni olamiz
                $lastPaidNorm = sprintf('%s-%02d', $lm[1], (int) $lm[2]);
            } elseif (preg_match('#^(\d{1,2})[/.\-](\d{4})$#', $lastPaid, $lm)) {
                // MM/YYYY yoki M/YYYY
                $lastPaidNorm = sprintf('%s-%02d', $lm[2], (int) $lm[1]);
            } elseif (is_numeric($lastPaid)) {
                try {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $lastPaid);
                    $lastPaidNorm = $dt->format('Y-m');
                } catch (\Throwable) {
                    $errors[] = "oxirgi_tolov: noto'g'ri format (YYYY-MM kutiladi)";
                }
            } else {
                $errors[] = "oxirgi_tolov: noto'g'ri format (YYYY-MM kutiladi)";
            }
        }

        return [
            'valid' => empty($errors),
            'data' => [
                'inn'              => $inn,
                'name'             => $name,
                'phone'            => $normalizedPhone ?? $phone,
                'phone2'           => $normalizedPhone2,
                'service_date'     => $normalizedDate ?? $serviceDate,
                'payment_type'     => $paymentType,
                'product_count'    => $productCountInt,
                'last_paid_period' => $lastPaidNorm,
            ],
            'errors' => $errors,
        ];
    }
}
