<?php

declare(strict_types=1);

namespace App\Service\Util;

final class EffectiveDayCalculator
{
    public static function compute(\DateTimeImmutable $today, \DateTimeImmutable $serviceDate): int
    {
        $day = (int) $serviceDate->format('d');
        $daysInMonth = (int) $today->format('t');

        return min($day, $daysInMonth);
    }
}
