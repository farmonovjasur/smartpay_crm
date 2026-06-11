<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Entity\Client;
use App\Enum\ClientStatus;
use App\Enum\PaymentType;

/**
 * Shared random data generators for property-based tests.
 *
 * All methods are static and use mt_rand() for reproducibility with seeded RNG.
 */
final class Generators
{
    private function __construct() {}

    /**
     * Generate a random Client entity (not persisted).
     */
    public static function randomClient(): Client
    {
        $client = new Client();
        $client->setInn(self::randomInn());
        $client->setName(self::randomName());
        $client->setPhone(self::randomPhone());
        $client->setServiceDate(self::randomServiceDate());
        $client->setPaymentType(self::randomPaymentType());
        $client->setProductCount(self::randomProductCount());
        $client->setStatus(self::randomStatus());

        return $client;
    }

    /**
     * Generate a random period in YYYY-MM format.
     */
    public static function randomPeriod(): string
    {
        $year = mt_rand(2020, 2030);
        $month = mt_rand(1, 12);

        return sprintf('%04d-%02d', $year, $month);
    }

    /**
     * Generate a random INN: 9 digits (yuridik shaxs) or 14 digits (jismoniy shaxs).
     */
    public static function randomInn(): string
    {
        $length = mt_rand(0, 1) === 0 ? 9 : 14;
        $inn = '';

        // First digit should not be 0 for realism
        $inn .= (string) mt_rand(1, 9);
        for ($i = 1; $i < $length; $i++) {
            $inn .= (string) mt_rand(0, 9);
        }

        return $inn;
    }

    /**
     * Generate a random Uzbekistan phone number in +998XXXXXXXXX format.
     */
    public static function randomPhone(): string
    {
        $digits = '';
        for ($i = 0; $i < 9; $i++) {
            $digits .= (string) mt_rand(0, 9);
        }

        return '+998' . $digits;
    }

    /**
     * Generate a random product count (1–100).
     */
    public static function randomProductCount(): int
    {
        return mt_rand(1, 100);
    }

    /**
     * Generate a random PaymentType enum value.
     */
    public static function randomPaymentType(): PaymentType
    {
        $cases = PaymentType::cases();

        return $cases[mt_rand(0, count($cases) - 1)];
    }

    /**
     * Generate a random ClientStatus enum value.
     */
    public static function randomStatus(): ClientStatus
    {
        $cases = ClientStatus::cases();

        return $cases[mt_rand(0, count($cases) - 1)];
    }

    /**
     * Generate a random service date (within the last 5 years).
     */
    public static function randomServiceDate(): \DateTimeImmutable
    {
        $daysAgo = mt_rand(0, 365 * 5);

        return new \DateTimeImmutable("-{$daysAgo} days");
    }

    /**
     * Generate a random name string.
     */
    public static function randomName(): string
    {
        $firstNames = ['Alisher', 'Bekzod', 'Doniyor', 'Eldor', 'Farrux', 'Gulnora', 'Hamid', 'Islom', 'Jasur', 'Kamol'];
        $lastNames = ['Karimov', 'Halimov', 'Toshmatov', 'Ergashev', 'Mirzayev', 'Xolmatov', 'Normatov', 'Azimov', 'Raximov', 'Sultonov'];

        $first = $firstNames[mt_rand(0, count($firstNames) - 1)];
        $last = $lastNames[mt_rand(0, count($lastNames) - 1)];

        return $first . ' ' . $last;
    }

    /**
     * Generate a random decimal amount (for testing payment/debt scenarios).
     */
    public static function randomAmount(int $min = 100_000, int $max = 10_000_000): string
    {
        return number_format(mt_rand($min, $max) / 100, 2, '.', '');
    }
}
