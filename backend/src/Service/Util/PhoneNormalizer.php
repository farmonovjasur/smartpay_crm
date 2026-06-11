<?php

declare(strict_types=1);

namespace App\Service\Util;

final class PhoneNormalizer
{
    /**
     * Normalize phone number to +998XXXXXXXXX format.
     * Returns null if the input cannot be normalized.
     */
    public static function normalize(string $phone): ?string
    {
        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);

        // Already in correct format
        if (preg_match('/^\+998\d{9}$/', $phone)) {
            return $phone;
        }

        // 998XXXXXXXXX (without +)
        if (preg_match('/^998(\d{9})$/', $phone, $m)) {
            return '+998' . $m[1];
        }

        // 9X XXXXXXX (9 digits, operator code first)
        if (preg_match('/^(9[0-9]\d{7})$/', $phone, $m)) {
            return '+998' . $m[1];
        }

        return null;
    }
}
