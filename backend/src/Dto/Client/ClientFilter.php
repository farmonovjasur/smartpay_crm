<?php

declare(strict_types=1);

namespace App\Dto\Client;

final class ClientFilter
{
    public int $page = 1;
    public int $pageSize = 20;
    public ?string $search = null;
    public ?string $paymentType = null;
    public ?string $status = null;
    public string $sort = 'id_desc';
}
