<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched when the system detects that the daily debt reconciliation
 * has not yet run today. Handled asynchronously so that the HTTP request
 * is not blocked.
 */
final class ReconcileDebtsAsync
{
    public function __construct(
        public readonly string $date,
    ) {
    }
}
