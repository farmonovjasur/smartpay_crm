<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Service\Auth\CsrfTokenManager;
use PHPUnit\Framework\TestCase;

class CsrfTokenManagerTest extends TestCase
{
    private CsrfTokenManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CsrfTokenManager();
    }

    public function testGenerateReturns64CharHexString(): void
    {
        $token = $this->manager->generate();

        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateProducesUniqueTokens(): void
    {
        $token1 = $this->manager->generate();
        $token2 = $this->manager->generate();

        $this->assertNotSame($token1, $token2);
    }

    public function testVerifyReturnsTrueWhenMatching(): void
    {
        $token = $this->manager->generate();

        $this->assertTrue($this->manager->verify($token, $token));
    }

    public function testVerifyReturnsFalseWhenMismatch(): void
    {
        $token1 = $this->manager->generate();
        $token2 = $this->manager->generate();

        $this->assertFalse($this->manager->verify($token1, $token2));
    }

    public function testVerifyReturnsFalseForEmptyCookie(): void
    {
        $this->assertFalse($this->manager->verify('', 'some-header'));
    }

    public function testVerifyReturnsFalseForEmptyHeader(): void
    {
        $this->assertFalse($this->manager->verify('some-cookie', ''));
    }

    public function testVerifyReturnsFalseForBothEmpty(): void
    {
        $this->assertFalse($this->manager->verify('', ''));
    }
}
