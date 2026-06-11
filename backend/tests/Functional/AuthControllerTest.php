<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for AuthController endpoints.
 *
 * Validates: Requirements 1.1-1.10
 */
class AuthControllerTest extends AbstractApiTestCase
{
    private const TEST_PASSWORD = 'SecurePass123!';

    // ─── Login ────────────────────────────────────────────────────────────────

    public function testLoginSuccessReturns200WithUserData(): void
    {
        $user = $this->createTestUser();

        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $user->getEmail(), 'password' => self::TEST_PASSWORD]));

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('user', $data);
        self::assertSame($user->getEmail(), $data['user']['email']);
        self::assertSame('admin', $data['user']['role']);
    }

    public function testLoginSuccessSets3CookiesWithCorrectAttributes(): void
    {
        $user = $this->createTestUser();

        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $user->getEmail(), 'password' => self::TEST_PASSWORD]));

        $response = $this->client->getResponse();
        $cookies = $response->headers->getCookies();

        $cookieMap = [];
        foreach ($cookies as $cookie) {
            $cookieMap[$cookie->getName()] = $cookie;
        }

        // access_token: HttpOnly, Secure, SameSite=None, Path=/api
        self::assertArrayHasKey('access_token', $cookieMap);
        $access = $cookieMap['access_token'];
        self::assertTrue($access->isHttpOnly());
        self::assertTrue($access->isSecure());
        self::assertSame('none', strtolower($access->getSameSite() ?? ''));
        self::assertSame('/api', $access->getPath());

        // refresh_token: HttpOnly, Secure, SameSite=None, Path=/api/auth
        self::assertArrayHasKey('refresh_token', $cookieMap);
        $refresh = $cookieMap['refresh_token'];
        self::assertTrue($refresh->isHttpOnly());
        self::assertTrue($refresh->isSecure());
        self::assertSame('none', strtolower($refresh->getSameSite() ?? ''));
        self::assertSame('/api/auth', $refresh->getPath());

        // csrf_token: NOT HttpOnly, Secure, SameSite=Strict, Path=/
        self::assertArrayHasKey('csrf_token', $cookieMap);
        $csrf = $cookieMap['csrf_token'];
        self::assertFalse($csrf->isHttpOnly());
        self::assertTrue($csrf->isSecure());
        self::assertSame('strict', strtolower($csrf->getSameSite() ?? ''));
        self::assertSame('/', $csrf->getPath());
    }

    public function testLoginWithInvalidCredentialsReturns401(): void
    {
        $user = $this->createTestUser();

        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $user->getEmail(), 'password' => 'wrong']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginWithNonExistentEmailReturns401(): void
    {
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => 'nobody@test.uz', 'password' => 'anything']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    // ─── Rate Limiting ────────────────────────────────────────────────────────

    public function testLoginRateLimitAfter5FailedAttemptsReturns429WithRetryAfter(): void
    {
        $user = $this->createTestUser();

        // 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->client->request('POST', '/api/auth/login', [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['email' => $user->getEmail(), 'password' => 'wrong_' . $i]));
        }

        // 6th attempt should be rate-limited
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $user->getEmail(), 'password' => self::TEST_PASSWORD]));

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertTrue($response->headers->has('Retry-After'));
    }

    // ─── Refresh Token Rotation ───────────────────────────────────────────────

    public function testRefreshRotatesToken(): void
    {
        $user = $this->createTestUser();

        // Login to get refresh token
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $user->getEmail(), 'password' => self::TEST_PASSWORD]));

        $loginResponse = $this->client->getResponse();
        $loginCookies = $this->extractCookieValues($loginResponse);
        $oldRefresh = $loginCookies['refresh_token'] ?? '';

        self::assertNotEmpty($oldRefresh);

        // Use refresh token
        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie('refresh_token', $oldRefresh, (string) (time() + 604800), '/api/auth')
        );

        $this->client->request('POST', '/api/auth/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $refreshResponse = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $refreshResponse->getStatusCode());

        $newCookies = $this->extractCookieValues($refreshResponse);
        $newRefresh = $newCookies['refresh_token'] ?? '';

        // New token should be different from old
        self::assertNotEmpty($newRefresh);
        self::assertNotSame($oldRefresh, $newRefresh);
    }

    // ─── Refresh Token Theft Detection ────────────────────────────────────────

    public function testRefreshTheftDetectionRevokesAllTokens(): void
    {
        $user = $this->createTestUser();

        // Login
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $user->getEmail(), 'password' => self::TEST_PASSWORD]));

        $loginCookies = $this->extractCookieValues($this->client->getResponse());
        $originalRefresh = $loginCookies['refresh_token'] ?? '';

        // First rotation (legitimate)
        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie('refresh_token', $originalRefresh, (string) (time() + 604800), '/api/auth')
        );
        $this->client->request('POST', '/api/auth/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // Replay old (revoked) token — simulates theft
        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie('refresh_token', $originalRefresh, (string) (time() + 604800), '/api/auth')
        );
        $this->client->request('POST', '/api/auth/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        // Verify all refresh tokens for user are revoked
        $tokens = $this->em->getRepository(RefreshToken::class)->findBy(['user' => $user]);
        foreach ($tokens as $token) {
            self::assertTrue($token->isRevoked(), 'All tokens should be revoked after theft detection');
        }
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function testLogoutClearsAllCookiesWithExpiredMaxAge(): void
    {
        $user = $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/auth/logout');

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $cookies = $response->headers->getCookies();
        $cookieMap = [];
        foreach ($cookies as $cookie) {
            $cookieMap[$cookie->getName()] = $cookie;
        }

        // All 3 cookies should be expired (timestamp in the past)
        foreach (['access_token', 'refresh_token', 'csrf_token'] as $name) {
            self::assertArrayHasKey($name, $cookieMap);
            // Expired cookie: expires timestamp is in the past or epoch=1
            self::assertLessThanOrEqual(time(), $cookieMap[$name]->getExpiresTime());
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createTestUser(string $email = 'admin@test.smartpay.uz'): User
    {
        $user = new User();
        $user->setName('Test Admin');
        $user->setEmail($email);
        $user->setRole(UserRole::Admin);

        $hasher = self::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, self::TEST_PASSWORD));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Extract cookie name→value map from response Set-Cookie headers.
     */
    private function extractCookieValues(\Symfony\Component\HttpFoundation\Response $response): array
    {
        $result = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $result[$cookie->getName()] = $cookie->getValue();
        }
        return $result;
    }
}
