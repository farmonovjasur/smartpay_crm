<?php

declare(strict_types=1);

namespace App\Enum;

enum LedgerStatus: string
{
    case Active = 'active';
    case Partial = 'partial';
    case Paid = 'paid';
}
