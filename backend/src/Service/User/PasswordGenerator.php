<?php

declare(strict_types=1);

namespace App\Service\User;

/**
 * Generates strong random passwords (min 12 chars, mixed case, digit).
 */
final class PasswordGenerator
{
    private const LENGTH = 16;

    public function generate(): string
    {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $digits = '0123456789';
        $special = '!@#$%^&*';
        $all = $upper . $lower . $digits . $special;

        // Ensure at least one of each required type
        $password = '';
        $password .= $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill the rest randomly
        for ($i = 4; $i < self::LENGTH; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Shuffle to avoid predictable positions
        return str_shuffle($password);
    }
}
