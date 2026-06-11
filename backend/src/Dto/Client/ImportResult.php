<?php

declare(strict_types=1);

namespace App\Dto\Client;

final class ImportResult
{
    public int $totalRows = 0;
    public int $importedCount = 0;
    /** @var ImportRowError[] */
    public array $errorRows = [];
    /** @var array{row: int, inn: string}[] */
    public array $duplicateRows = [];
}
