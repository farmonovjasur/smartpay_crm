<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Util\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    public static function validProvider(): \Generator
    {
        yield ['+998901234567', '+998901234567'];
        yield ['998901234567', '+998901234567'];
        yield ['901234567', '+998901234567'];
    }

    /** @dataProvider validProvider */
    public function testNormalize(string $input, string $expected): void
    {
        self::assertEquals($expected, PhoneNormalizer::normalize($input));
    }

    public static function invalidProvider(): \Generator
    {
        yield ['123'];
        yield [''];
        yield ['abc'];
    }

    /** @dataProvider invalidProvider */
    public function testReturnsNullForInvalid(string $input): void
    {
        self::assertNull(PhoneNormalizer::normalize($input));
    }
}
