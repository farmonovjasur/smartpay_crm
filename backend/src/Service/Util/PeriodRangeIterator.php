<?php

declare(strict_types=1);

namespace App\Service\Util;

final class PeriodRangeIterator
{
    /**
     * @return iterable<string> Yields YYYY-MM periods from $from to $to inclusive
     */
    public static function between(string $from, string $to): iterable
    {
        $current = \DateTimeImmutable::createFromFormat('Y-m-d', $from . '-01');
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', $to . '-01');

        while ($current <= $end) {
            yield $current->format('Y-m');
            $current = $current->modify('+1 month');
        }
    }
}
