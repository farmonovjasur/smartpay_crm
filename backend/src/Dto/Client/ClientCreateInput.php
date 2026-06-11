<?php

declare(strict_types=1);

namespace App\Dto\Client;

use App\Validator\InnConstraint;
use App\Validator\PeriodConstraint;
use App\Validator\PhoneConstraint;
use Symfony\Component\Validator\Constraints as Assert;

final class ClientCreateInput
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

    public ?string $notes = null;

    /**
     * Mandatory. The most recent month for which the client has already paid.
     * The application will retroactively mark every month from service_date
     * through this period as paid in client_monthly_status (so the daily
     * debt check does not flag those months). Format: YYYY-MM.
     */
    #[Assert\NotBlank(message: "Oxirgi to'langan davr majburiy maydon")]
    #[PeriodConstraint]
    public string $lastPaidPeriod = '';
}
