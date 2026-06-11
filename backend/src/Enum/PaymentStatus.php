<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Skipped = 'skipped';
}
