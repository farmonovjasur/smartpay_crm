<?php

declare(strict_types=1);

namespace App\Dto\Debt;

final class DetectionReport
{
    public int $createdCount = 0;
    public int $incrementedCount = 0;
    public int $processedClientsCount = 0;
    public int $balanceDeductedCount = 0;
}
