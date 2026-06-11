<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class RefreshTokenStore
{
    private const TTL = 604800; // 7 days

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Issues a new refresh token for the user.
     * Returns the plaintext token; the DB stores only the SHA-256 hash.
     */
    public function issue(User $u, ?string $userAgent, ?string $ip): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainToken);

        $rt = new RefreshToken();
        $rt->setUser($u);
        $rt->setTokenHash($hash);
        $rt->setExpiresAt(new \DateTimeImmutable('+' . self::TTL . ' seconds'));
        $rt->setUserAgent($userAgent);
        $rt->setIp($ip);

        $this->em->persist($rt);
        $this->em->flush();

        return $plainToken;
    }

    /**
     * Validates a plaintext refresh token.
     *
     * @throws \InvalidArgumentException if token is invalid, expired, or revoked
     */
    public function validate(string $plainToken): RefreshToken
    {
        $hash = hash('sha256', $plainToken);

        $rt = $this->em->getRepository(RefreshToken::class)->findOneBy([
            'tokenHash' => $hash,
        ]);

        if ($rt === null) {
            throw new \InvalidArgumentException('Invalid refresh token.');
        }

        if ($rt->isRevoked()) {
            throw new \InvalidArgumentException('Refresh token has been revoked.');
        }

        if ($rt->isExpired()) {
            throw new \InvalidArgumentException('Refresh token has expired.');
        }

        return $rt;
    }

    /**
     * Rotates a refresh token: revokes the old one and issues a new one for the same user.
     * Returns the new plaintext token.
     */
    public function rotate(RefreshToken $old): string
    {
        $old->revoke();
        $this->em->flush();

        return $this->issue(
            $old->getUser(),
            $old->getUserAgent(),
            $old->getIp(),
        );
    }

    /**
     * Revokes a single refresh token.
     */
    public function revoke(RefreshToken $rt): void
    {
        $rt->revoke();
        $this->em->flush();
    }

    /**
     * Revokes all refresh tokens for a given user.
     */
    public function revokeAllForUser(User $u): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->update(RefreshToken::class, 'rt')
            ->set('rt.revokedAt', ':now')
            ->where('rt.user = :user')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $u);

        $qb->getQuery()->execute();
    }
}
