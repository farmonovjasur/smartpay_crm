<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class RateLimitExceededException extends TooManyRequestsHttpException
{
    public function __construct(int $retryAfter)
    {
        parent::__construct($retryAfter, 'Too many login attempts. Please try again later.');
    }
}
