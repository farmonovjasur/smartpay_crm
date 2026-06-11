<?php

declare(strict_types=1);

namespace App\Dto\Client;

use App\Validator\PeriodConstraint;
use Symfony\Component\Validator\Constraints as Assert;

final class MarkPaidRequest
{
    #[Assert\NotBlank]
    #[PeriodConstraint]
    public string $period = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['fakt', 'naqt'])]
    public string $method = '';
}
