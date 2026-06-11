<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ExcelTooLargeException extends HttpException
{
    public function __construct()
    {
        parent::__construct(413, 'File too large. Maximum size is 5MB.');
    }
}
