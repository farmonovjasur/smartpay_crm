<?php

declare(strict_types=1);

namespace App\Dto\Client;

final class ImportRowError
{
    public function __construct(
        public readonly int $row,
        public readonly array $errors,
    ) {
    }
}
