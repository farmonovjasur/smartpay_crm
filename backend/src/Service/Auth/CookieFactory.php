<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\HttpFoundation\Cookie;

final class CookieFactory
{
    /**
     * Creates the access_token cookie (HttpOnly, Secure, SameSite=None, Path=/api).
     */
    public function access(string $jwt, int $ttl): Cookie
    {
        return Cookie::create('access_token')
            ->withValue($jwt)
            ->withHttpOnly(true)
            ->withSecure(false)
            ->withSameSite('lax')
            ->withPath('/api')
            ->withExpires(time() + $ttl);
    }

    /**
     * Creates the refresh_token cookie (HttpOnly, Secure, SameSite=None, Path=/api/auth).
     */
    public function refresh(string $token, int $ttl): Cookie
    {
        return Cookie::create('refresh_token')
            ->withValue($token)
            ->withHttpOnly(true)
            ->withSecure(false)
            ->withSameSite('lax')
            ->withPath('/api/auth')
            ->withExpires(time() + $ttl);
    }

    /**
     * Creates the csrf_token cookie (NOT HttpOnly — JS must read it, Secure, SameSite=Strict, Path=/).
     */
    public function csrf(string $token, int $ttl): Cookie
    {
        return Cookie::create('csrf_token')
            ->withValue($token)
            ->withHttpOnly(false)
            ->withSecure(false)
            ->withSameSite('strict')
            ->withPath('/')
            ->withExpires(time() + $ttl);
    }

    /**
     * Creates an expired cookie to clear it from the browser (Max-Age=0).
     */
    public function expired(string $name): Cookie
    {
        return Cookie::create($name)
            ->withValue('')
            ->withExpires(1)
            ->withPath($this->pathForName($name))
            ->withHttpOnly($name !== 'csrf_token')
            ->withSecure(false)
            ->withSameSite($name === 'csrf_token' ? 'strict' : 'lax');
    }

    private function pathForName(string $name): string
    {
        return match ($name) {
            'access_token' => '/api',
            'refresh_token' => '/api/auth',
            'csrf_token' => '/',
            default => '/',
        };
    }
}
