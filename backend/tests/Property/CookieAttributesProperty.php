<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\Auth\AuthService;
use App\Service\Auth\CookieFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Property 13: Muvaffaqiyatli login → cookie atributlari aniq qiymatlarga teng.
 *
 * For any successfully authenticated user, the 3 cookies produced by CookieFactory
 * always have the exact correct attributes as defined in the design document.
 *
 * **Validates: Requirements 1.2**
 */
class CookieAttributesProperty extends PropertyTestCase
{
    public function testCookieAttributesMatchDesignSpec(): void
    {
        $cookieFactory = self::getContainer()->get(CookieFactory::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $authService = self::getContainer()->get(AuthService::class);

        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            // Create random user
            $user = new User();
            $user->setName(Generators::randomName());
            $user->setEmail('prop13_' . $i . '_' . uniqid() . '@test.uz');
            $user->setRole($this->randomInt(0, 1) === 0 ? UserRole::Admin : UserRole::User);
            $password = 'Pass' . bin2hex(random_bytes(4)) . '!';
            $user->setPasswordHash($hasher->hashPassword($user, $password));

            $this->em->persist($user);
            $this->em->flush();

            // Login to get session — use a unique IP per iteration so the
            // login rate limiter (5 attempts / 15 min per IP) never trips.
            $uniqueIp = sprintf('10.%d.%d.%d', ($i >> 16) & 0xFF, ($i >> 8) & 0xFF, $i & 0xFF);
            $session = $authService->login($user->getEmail(), $password, $uniqueIp);

            // Verify access_token cookie attributes
            $accessCookie = $cookieFactory->access($session->accessToken, $session->accessTtl);
            self::assertTrue($accessCookie->isHttpOnly(), "access_token must be HttpOnly (iter=$i)");
            self::assertTrue($accessCookie->isSecure(), "access_token must be Secure (iter=$i)");
            self::assertSame('none', strtolower($accessCookie->getSameSite() ?? ''), "access_token SameSite=None (iter=$i)");
            self::assertSame('/api', $accessCookie->getPath(), "access_token Path=/api (iter=$i)");

            // Verify refresh_token cookie attributes
            $refreshCookie = $cookieFactory->refresh($session->refreshToken, $session->refreshTtl);
            self::assertTrue($refreshCookie->isHttpOnly(), "refresh_token must be HttpOnly (iter=$i)");
            self::assertTrue($refreshCookie->isSecure(), "refresh_token must be Secure (iter=$i)");
            self::assertSame('none', strtolower($refreshCookie->getSameSite() ?? ''), "refresh_token SameSite=None (iter=$i)");
            self::assertSame('/api/auth', $refreshCookie->getPath(), "refresh_token Path=/api/auth (iter=$i)");

            // Verify csrf_token cookie attributes
            $csrfCookie = $cookieFactory->csrf($session->csrfToken, $session->accessTtl);
            self::assertFalse($csrfCookie->isHttpOnly(), "csrf_token must NOT be HttpOnly (iter=$i)");
            self::assertTrue($csrfCookie->isSecure(), "csrf_token must be Secure (iter=$i)");
            self::assertSame('strict', strtolower($csrfCookie->getSameSite() ?? ''), "csrf_token SameSite=Strict (iter=$i)");
            self::assertSame('/', $csrfCookie->getPath(), "csrf_token Path=/ (iter=$i)");

            // Clear rate limiter state by resetting between iterations
            $this->em->clear();
        }
    }
}
