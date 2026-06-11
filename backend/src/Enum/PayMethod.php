<?php

declare(strict_types=1);

namespace App\Enum;

enum PayMethod: string
{
    case Fakt = 'fakt';
    case Naqt = 'naqt';
}
