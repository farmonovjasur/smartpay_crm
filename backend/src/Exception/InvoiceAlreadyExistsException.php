<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class InvoiceAlreadyExistsException extends ConflictHttpException
{
    public function __construct(string $invoiceNumber)
    {
        parent::__construct("Invoice already exists for this period: $invoiceNumber");
    }
}
