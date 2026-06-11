<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Service\Auth\CookieFactory;
use PHPUnit\Framework\TestCase;

class CookieFactoryTest extends TestCase
{
    private CookieFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new CookieFactory();
    }

    public function testAccessCookieAttributes(): void
    {
        $cookie = $this->factory->access('jwt-value', 900);

        $this->assertSame('access_token', $cookie->getName());
        $this->assertSame('jwt-value', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('none', $cookie->getSameSite());
        $this->assertSame('/api', $cookie->getPath());
    }

    public function testRefreshCookieAttributes(): void
    {
        $cookie = $this->factory->refresh('refresh-value', 604800);

        $this->assertSame('refresh_token', $cookie->getName());
        $this->assertSame('refresh-value', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('none', $cookie->getSameSite());
        $this->assertSame('/api/auth', $cookie->getPath());
    }

    public function testCsrfCookieAttributes(): void
    {
        $cookie = $this->factory->csrf('csrf-value', 900);

        $this->assertSame('csrf_token', $cookie->getName());
        $this->assertSame('csrf-value', $cookie->getValue());
        $this->assertFalse($cookie->isHttpOnly()); // JS must read it
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
    }

    public function testExpiredAccessTokenCookie(): void
    {
        $cookie = $this->factory->expired('access_token');

        $this->assertSame('access_token', $cookie->getName());
        $this->assertSame('', $cookie->getValue());
        $this->assertSame(1, $cookie->getExpiresTime());
        $this->assertSame('/api', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
    }

    public function testExpiredRefreshTokenCookie(): void
    {
        $cookie = $this->factory->expired('refresh_token');

        $this->assertSame('refresh_token', $cookie->getName());
        $this->assertSame('/api/auth', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function testExpiredCsrfTokenCookie(): void
    {
        $cookie = $this->factory->expired('csrf_token');

        $this->assertSame('csrf_token', $cookie->getName());
        $this->assertSame('/', $cookie->getPath());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
    }
}
