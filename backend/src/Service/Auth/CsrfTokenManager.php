<?php

declare(strict_types=1);

namespace App\Service\Auth;

final class CsrfTokenManager
{
    /**
     * Generates a cryptographically secure random 32-byte hex string.
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Verifies that the CSRF cookie value matches the header value
     * using timing-safe comparison.
     */
    public function verify(string $cookie, string $header): bool
    {
        if ($cookie === '' || $header === '') {
            return false;
        }

        return hash_equals($cookie, $header);
    }
}
