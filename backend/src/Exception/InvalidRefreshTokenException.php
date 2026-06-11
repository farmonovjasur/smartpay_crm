<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class InvalidRefreshTokenException extends UnauthorizedHttpException
{
    public function __construct(string $message = 'Invalid or expired refresh token.')
    {
        parent::__construct('', $message);
    }
}
