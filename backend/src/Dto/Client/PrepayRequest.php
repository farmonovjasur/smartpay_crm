<?php

declare(strict_types=1);

namespace App\Dto\Client;

use Symfony\Component\Validator\Constraints as Assert;

final class PrepayRequest
{
    #[Assert\NotBlank(message: "Summa majburiy maydon")]
    #[Assert\Positive(message: "Summa musbat son bo'lishi kerak")]
    public string $amount = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['fakt', 'naqt'])]
    public string $method = '';

    public ?string $notes = null;
}
