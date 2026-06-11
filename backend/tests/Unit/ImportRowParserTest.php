<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Client\ImportRowParser;
use PHPUnit\Framework\TestCase;

final class ImportRowParserTest extends TestCase
{
    private ImportRowParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ImportRowParser();
    }

    public function testValidRow(): void
    {
        $result = $this->parser->parse(['2025-06-15', '123456789', 'Test Corp', '+998901234567', 'fakt', '3']);
        self::assertTrue($result['valid']);
        self::assertEmpty($result['errors']);
        self::assertEquals('123456789', $result['data']['inn']);
        self::assertEquals('+998901234567', $result['data']['phone']);
        self::assertEquals(3, $result['data']['product_count']);
    }

    public function testInvalidInn(): void
    {
        $result = $this->parser->parse(['2025-06-15', '123', 'Test', '+998901234567', 'fakt', '1']);
        self::assertFalse($result['valid']);
        self::assertNotEmpty($result['errors']);
    }

    public function testInvalidPaymentType(): void
    {
        $result = $this->parser->parse(['2025-06-15', '123456789', 'Test', '+998901234567', 'invalid', '1']);
        self::assertFalse($result['valid']);
    }

    public function testPhoneNormalization(): void
    {
        $result = $this->parser->parse(['2025-06-15', '123456789', 'Test', '998901234567', 'naqt', '2']);
        self::assertTrue($result['valid']);
        self::assertEquals('+998901234567', $result['data']['phone']);
    }
}
