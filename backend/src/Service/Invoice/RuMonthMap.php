<?php

declare(strict_types=1);

namespace App\Service\Invoice;

final class RuMonthMap
{
    private const MONTHS = [
        '01' => 'Январь',
        '02' => 'Февраль',
        '03' => 'Март',
        '04' => 'Апрель',
        '05' => 'Май',
        '06' => 'Июнь',
        '07' => 'Июль',
        '08' => 'Август',
        '09' => 'Сентябрь',
        '10' => 'Октябрь',
        '11' => 'Ноябрь',
        '12' => 'Декабрь',
    ];

    public static function get(string $monthNum): string
    {
        return self::MONTHS[$monthNum] ?? throw new \InvalidArgumentException("Invalid month: $monthNum");
    }
}
