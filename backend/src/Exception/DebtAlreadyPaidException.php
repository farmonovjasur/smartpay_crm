<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DebtAlreadyPaidException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Debt is already paid.');
    }
}
