<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\Auth\JwtTokenManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;

class JwtTokenManagerTest extends TestCase
{
    public function testCreateAccessTokenPassesCorrectClaims(): void
    {
        $user = $this->createUser(1, 'test@example.com', UserRole::Admin);

        $capturedPayload = null;
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->once())
            ->method('createFromPayload')
            ->willReturnCallback(function (User $u, array $payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return 'fake-jwt-token';
            });

        $manager = new JwtTokenManager($jwtManager);
        $token = $manager->createAccessToken($user);

        $this->assertSame('fake-jwt-token', $token);
        $this->assertArrayHasKey('iat', $capturedPayload);
        $this->assertArrayHasKey('exp', $capturedPayload);
        $this->assertArrayHasKey('sub', $capturedPayload);
        $this->assertArrayHasKey('email', $capturedPayload);
        $this->assertArrayHasKey('role', $capturedPayload);
        $this->assertArrayHasKey('jti', $capturedPayload);

        // exp = iat + 900
        $this->assertSame($capturedPayload['iat'] + 900, $capturedPayload['exp']);
        $this->assertSame('1', $capturedPayload['sub']);
        $this->assertSame('test@example.com', $capturedPayload['email']);
        $this->assertSame('admin', $capturedPayload['role']);
        // jti is 32 hex chars (16 random bytes)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $capturedPayload['jti']);
    }

    private function createUser(int $id, string $email, UserRole $role): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRole($role);
        $user->setName('Test User');
        $user->setPasswordHash('hashed');

        // Use reflection to set the ID
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }
}
