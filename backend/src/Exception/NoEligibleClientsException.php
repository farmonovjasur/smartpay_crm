<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class NoEligibleClientsException extends UnprocessableEntityHttpException
{
    public function __construct()
    {
        parent::__construct('No eligible clients found for invoice generation.');
    }
}
