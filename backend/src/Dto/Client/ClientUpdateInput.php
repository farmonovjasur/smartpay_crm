<?php

declare(strict_types=1);

namespace App\Dto\Client;

use App\Validator\InnConstraint;
use App\Validator\PeriodConstraint;
use App\Validator\PhoneConstraint;
use Symfony\Component\Validator\Constraints as Assert;

final class ClientUpdateInput
{
    #[Assert\NotBlank]
    #[InnConstraint]
    public string $inn = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\NotBlank]
    #[PhoneConstraint]
    public string $phone = '';

    #[PhoneConstraint]
    public ?string $phone2 = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $serviceDate = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['fakt', 'naqt', 'qarz'])]
    public string $paymentType = '';

    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $productCount = 1;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['faol', 'nofaol'])]
    public string $status = 'faol';

    public ?string $notes = null;

    /**
     * Mandatory on update. Moving it backward is rejected with 409 Conflict
     * to preserve audit history; moving it forward seeds additional CMS rows.
     * Format: YYYY-MM.
     */
    #[Assert\NotBlank(message: "Oxirgi to'langan davr majburiy maydon")]
    #[PeriodConstraint]
    public string $lastPaidPeriod = '';
}
