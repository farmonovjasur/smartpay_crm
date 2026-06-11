<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use App\Entity\User;

final readonly class AuthSession
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $csrfToken,
        public int    $accessTtl,
        public int    $refreshTtl,
        public User   $user,
    ) {
    }
}
