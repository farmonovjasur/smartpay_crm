<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class JwtTokenManager
{
    private const TTL = 900;

    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function createAccessToken(User $u): string
    {
        $now = time();

        return $this->jwtManager->createFromPayload($u, [
            'iat' => $now,
            'exp' => $now + self::TTL,
            'sub' => (string) $u->getId(),
            'email' => $u->getEmail(),
            'role' => $u->getRole()->value,
            'jti' => bin2hex(random_bytes(16)),
        ]);
    }
}
