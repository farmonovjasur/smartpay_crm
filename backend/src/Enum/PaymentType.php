<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentType: string
{
    case Fakt = 'fakt';
    case Naqt = 'naqt';
    case Qarz = 'qarz';
}
