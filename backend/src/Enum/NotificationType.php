<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case DebtCreated = 'debt_created';
    case InvoiceGenerated = 'invoice_generated';
    case ClientImported = 'client_imported';
    case System = 'system';
}
