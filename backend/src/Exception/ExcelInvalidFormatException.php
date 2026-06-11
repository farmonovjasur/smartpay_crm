<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ExcelInvalidFormatException extends BadRequestHttpException
{
    public function __construct()
    {
        parent::__construct('Invalid file format. Only .xlsx files are accepted.');
    }
}
