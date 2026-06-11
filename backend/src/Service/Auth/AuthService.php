<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Dto\Auth\AuthSession;
use App\Entity\User;
use App\Exception\InvalidCredentialsException;
use App\Exception\InvalidRefreshTokenException;
use App\Exception\RateLimitExceededException;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class AuthService
{
    private const ACCESS_TTL = 900;       // 15 minutes
    private const REFRESH_TTL = 604800;   // 7 days

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly RefreshTokenStore $refreshTokenStore,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly RateLimiterFactory $loginLimiter,
    ) {
    }

    /**
     * Authenticates a user by email and password.
     *
     * @throws InvalidCredentialsException
     * @throws RateLimitExceededException
     */
    public function login(string $email, string $password, string $ip): AuthSession
    {
        // Rate limiting by IP
        $limiter = $this->loginLimiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
            throw new RateLimitExceededException(max(1, $retryAfter));
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user === null || $user->isDeleted() || !$user->isActive()) {
            throw new InvalidCredentialsException();
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new InvalidCredentialsException();
        }

        // Update last login info
        $user->setLastLoginAt(new \DateTimeImmutable());
        $user->setLastLoginIp($ip);

        return $this->createSession($user, $ip);
    }

    /**
     * Refreshes an authentication session using a refresh token.
     * Implements token rotation and theft detection.
     *
     * @throws InvalidRefreshTokenException
     */
    public function refresh(string $refreshToken): AuthSession
    {
        try {
            $rt = $this->refreshTokenStore->validate($refreshToken);
        } catch (\InvalidArgumentException $e) {
            // Token theft detection: if token was already revoked, revoke all user's tokens
            $hash = hash('sha256', $refreshToken);
            $existing = $this->findRevokedTokenByHash($hash);
            if ($existing !== null) {
                $this->refreshTokenStore->revokeAllForUser($existing->getUser());
            }

            throw new InvalidRefreshTokenException($e->getMessage());
        }

        $user = $rt->getUser();

        if ($user->isDeleted() || !$user->isActive()) {
            $this->refreshTokenStore->revoke($rt);
            throw new InvalidRefreshTokenException('User account is disabled.');
        }

        // Rotate: revoke old token, issue new one
        $newPlainToken = $this->refreshTokenStore->rotate($rt);

        $accessToken = $this->jwtTokenManager->createAccessToken($user);
        $csrfToken = $this->csrfTokenManager->generate();

        return new AuthSession(
            accessToken: $accessToken,
            refreshToken: $newPlainToken,
            csrfToken: $csrfToken,
            accessTtl: self::ACCESS_TTL,
            refreshTtl: self::REFRESH_TTL,
            user: $user,
        );
    }

    /**
     * Logs out a user by revoking their refresh token.
     */
    public function logout(string $refreshToken): void
    {
        try {
            $rt = $this->refreshTokenStore->validate($refreshToken);
            $this->refreshTokenStore->revoke($rt);
        } catch (\InvalidArgumentException) {
            // Token already invalid — silently succeed
        }
    }

    private function createSession(User $user, string $ip): AuthSession
    {
        $accessToken = $this->jwtTokenManager->createAccessToken($user);
        $refreshToken = $this->refreshTokenStore->issue($user, null, $ip);
        $csrfToken = $this->csrfTokenManager->generate();

        return new AuthSession(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            csrfToken: $csrfToken,
            accessTtl: self::ACCESS_TTL,
            refreshTtl: self::REFRESH_TTL,
            user: $user,
        );
    }

    private function findRevokedTokenByHash(string $hash): ?\App\Entity\RefreshToken
    {
        $em = $this->userRepository->getEntityManager();
        return $em->getRepository(\App\Entity\RefreshToken::class)->findOneBy([
            'tokenHash' => $hash,
        ]);
    }
}
