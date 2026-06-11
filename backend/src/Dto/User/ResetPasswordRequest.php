<?php

declare(strict_types=1);

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordRequest
{
    #[Assert\Length(min: 8, max: 255)]
    public ?string $newPassword = null;
}
