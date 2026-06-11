<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class InvalidCredentialsException extends UnauthorizedHttpException
{
    public function __construct(string $message = 'Invalid email or password.')
    {
        parent::__construct('', $message);
    }
}
