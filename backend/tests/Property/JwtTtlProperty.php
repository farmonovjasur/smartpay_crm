<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\Auth\JwtTokenManager;
use App\Service\Auth\RefreshTokenStore;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Property 12: Barcha access tokenlar `exp ≤ iat + 900`,
 * refresh tokenlar `exp ≤ iat + 7×86400`.
 *
 * For any generated JWT, the TTL boundary is always respected.
 * For any issued refresh token, expiration is within 7 days.
 *
 * **Validates: Requirements 1.1**
 */
class JwtTtlProperty extends PropertyTestCase
{
    private const ACCESS_MAX_TTL = 900;        // 15 minutes
    private const REFRESH_MAX_TTL = 604800;    // 7 days

    public function testAccessTokenExpNeverExceedsIatPlus900(): void
    {
        $jwtManager = self::getContainer()->get(JwtTokenManager::class);
        $jwtEncoder = self::getContainer()->get(JWTEncoderInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            $user = new User();
            $user->setName(Generators::randomName());
            $user->setEmail('jwt_ttl_' . $i . '_' . uniqid() . '@test.uz');
            $user->setRole($this->randomInt(0, 1) === 0 ? UserRole::Admin : UserRole::User);
            $user->setPasswordHash($hasher->hashPassword($user, 'dummy'));

            $this->em->persist($user);
            $this->em->flush();

            $token = $jwtManager->createAccessToken($user);
            $payload = $jwtEncoder->decode($token);

            self::assertArrayHasKey('iat', $payload, "JWT must have iat claim (iter=$i)");
            self::assertArrayHasKey('exp', $payload, "JWT must have exp claim (iter=$i)");

            $ttl = $payload['exp'] - $payload['iat'];
            self::assertLessThanOrEqual(
                self::ACCESS_MAX_TTL,
                $ttl,
                "Access token TTL ($ttl) exceeds maximum " . self::ACCESS_MAX_TTL . " seconds (iter=$i)"
            );
            self::assertGreaterThan(0, $ttl, "TTL must be positive (iter=$i)");

            $this->em->clear();
        }
    }

    public function testRefreshTokenExpiresWithin7Days(): void
    {
        $refreshStore = self::getContainer()->get(RefreshTokenStore::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            $user = new User();
            $user->setName(Generators::randomName());
            $user->setEmail('refresh_ttl_' . $i . '_' . uniqid() . '@test.uz');
            $user->setRole(UserRole::Admin);
            $user->setPasswordHash($hasher->hashPassword($user, 'dummy'));

            $this->em->persist($user);
            $this->em->flush();

            $beforeIssue = new \DateTimeImmutable();
            $plainToken = $refreshStore->issue($user, 'TestAgent', '127.0.0.1');

            // Validate the token to get the entity
            $rt = $refreshStore->validate($plainToken);
            $expiresAt = $rt->getExpiresAt();

            $maxAllowed = $beforeIssue->modify('+' . self::REFRESH_MAX_TTL . ' seconds');

            // Allow 5 seconds tolerance for execution time
            self::assertLessThanOrEqual(
                $maxAllowed->getTimestamp() + 5,
                $expiresAt->getTimestamp(),
                "Refresh token expiry exceeds 7 days (iter=$i)"
            );

            self::assertGreaterThan(
                $beforeIssue->getTimestamp(),
                $expiresAt->getTimestamp(),
                "Refresh token must expire in the future (iter=$i)"
            );

            $this->em->clear();
        }
    }
}
