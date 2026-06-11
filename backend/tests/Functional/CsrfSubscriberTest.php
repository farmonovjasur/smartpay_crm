<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for CsrfSubscriber.
 *
 * Validates: Requirements 1.5, 11.7
 */
class CsrfSubscriberTest extends AbstractApiTestCase
{
    public function testStateChangingRequestWithMismatchedCsrfReturns403(): void
    {
        $this->loginAs('admin');

        // Set a mismatched CSRF: cookie says one thing, header says another
        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie('csrf_token', 'cookie_value_abc', (string) (time() + 900), '/')
        );

        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'different_header_value',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testStateChangingRequestWithMissingCsrfHeaderReturns403(): void
    {
        $this->loginAs('admin');

        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie('csrf_token', 'some_token', (string) (time() + 900), '/')
        );

        // No X-CSRF-Token header sent
        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testStateChangingRequestWithMissingCsrfCookieReturns403(): void
    {
        $this->loginAs('admin');

        // Remove CSRF cookie
        $this->client->getCookieJar()->expire('csrf_token');

        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'some_value',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginWorksWithoutCsrfToken(): void
    {
        $user = $this->createUserForLogin();

        // Remove any CSRF cookie
        $this->client->getCookieJar()->expire('csrf_token');

        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $user->getEmail(), 'password' => 'TestPassword123!']));

        // Should succeed (200) or fail auth (401) — but NOT 403 CSRF
        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(Response::HTTP_FORBIDDEN, $statusCode);
        self::assertSame(Response::HTTP_OK, $statusCode);
    }

    public function testRefreshWorksWithoutCsrfToken(): void
    {
        // Login first to get a refresh token
        $user = $this->createUserForLogin();

        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $user->getEmail(), 'password' => 'TestPassword123!']));

        $cookies = $this->client->getResponse()->headers->getCookies();
        $refreshValue = null;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'refresh_token') {
                $refreshValue = $cookie->getValue();
            }
        }

        self::assertNotNull($refreshValue);

        // Remove CSRF cookie, set refresh token, and call refresh
        $this->client->getCookieJar()->expire('csrf_token');
        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie('refresh_token', $refreshValue, (string) (time() + 604800), '/api/auth')
        );

        $this->client->request('POST', '/api/auth/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $statusCode = $this->client->getResponse()->getStatusCode();
        // Should NOT be 403
        self::assertNotSame(Response::HTTP_FORBIDDEN, $statusCode);
        self::assertSame(Response::HTTP_OK, $statusCode);
    }

    public function testGetRequestBypassesCsrfCheck(): void
    {
        $this->loginAs('admin');

        // Clear CSRF cookie
        $this->client->getCookieJar()->expire('csrf_token');

        $this->client->request('GET', '/api/auth/me', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // GET should work without CSRF
        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertNotSame(Response::HTTP_FORBIDDEN, $statusCode);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createUserForLogin(): \App\Entity\User
    {
        $user = new \App\Entity\User();
        $user->setName('CSRF Test User');
        $user->setEmail('csrf_test_' . uniqid() . '@test.smartpay.uz');
        $user->setRole(\App\Enum\UserRole::Admin);

        $hasher = self::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, 'TestPassword123!'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
