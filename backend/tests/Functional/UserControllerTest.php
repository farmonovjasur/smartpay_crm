<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\UserRole;

/**
 * Functional tests for UserController endpoints.
 *
 * Tests CRUD operations, email duplicate → 409, reset-password
 * (server generated and client provided), soft delete with refresh token revoke.
 */
class UserControllerTest extends AbstractApiTestCase
{
    // ─── LIST ────────────────────────────────────────────────────────

    public function testListUsersAsAdmin(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('GET', '/api/users');

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $json = $this->getJsonResponse();
        self::assertArrayHasKey('data', $json);
        self::assertArrayHasKey('total', $json);
        self::assertGreaterThanOrEqual(1, $json['total']);
    }

    public function testListUsersAsUserForbidden(): void
    {
        $this->loginAs('user');

        $this->jsonRequest('GET', '/api/users');

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testListUsersWithSearch(): void
    {
        $admin = $this->loginAs('admin');

        // Create additional user
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'Searchable User',
            'email' => 'searchable@test.com',
            'password' => 'StrongPass123!',
            'role' => 'user',
        ]);

        // Debug: check first request response
        $firstResponse = $this->client->getResponse();
        
        $this->jsonRequest('GET', '/api/users?search=Searchable');

        $response = $this->client->getResponse();
        $json = $this->getJsonResponse();
        self::assertSame(200, $response->getStatusCode(), 'Response: ' . $response->getContent());
    }

    // ─── SHOW ────────────────────────────────────────────────────────

    public function testShowUserAsAdmin(): void
    {
        $admin = $this->loginAs('admin');

        $this->jsonRequest('GET', '/api/users/' . $admin->getId());

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $json = $this->getJsonResponse();
        self::assertSame($admin->getId(), $json['data']['id']);
        self::assertSame($admin->getEmail(), $json['data']['email']);
    }

    public function testShowNonExistentUser(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('GET', '/api/users/99999');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public function testCreateUserAsAdmin(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/users', [
            'name' => 'New User',
            'email' => 'newuser@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);

        $response = $this->client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $json = $this->getJsonResponse();
        self::assertSame('New User', $json['data']['name']);
        self::assertSame('newuser@smartpay.uz', $json['data']['email']);
        self::assertSame('user', $json['data']['role']);
    }

    public function testCreateUserAsUserForbidden(): void
    {
        $this->loginAs('user');

        $this->jsonRequest('POST', '/api/users', [
            'name' => 'Attempt',
            'email' => 'attempt@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateUserDuplicateEmail409(): void
    {
        $this->loginAs('admin');

        // Create first user
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'First',
            'email' => 'duplicate@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        // Attempt to create second user with same email
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'Second',
            'email' => 'duplicate@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateUserValidationErrors(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/users', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'role' => 'invalid',
        ]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());

        $json = $this->getJsonResponse();
        self::assertArrayHasKey('errors', $json);
    }

    // ─── UPDATE ──────────────────────────────────────────────────────

    public function testUpdateUserAsAdmin(): void
    {
        $this->loginAs('admin');

        // Create a user to update
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'Original',
            'email' => 'original@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);
        $created = $this->getJsonResponse();
        $userId = $created['data']['id'];

        // Update
        $this->jsonRequest('PUT', '/api/users/' . $userId, [
            'name' => 'Updated Name',
            'email' => 'updated@smartpay.uz',
            'role' => 'admin',
            'is_active' => false,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $json = $this->getJsonResponse();
        self::assertSame('Updated Name', $json['data']['name']);
        self::assertSame('updated@smartpay.uz', $json['data']['email']);
        self::assertSame('admin', $json['data']['role']);
        self::assertFalse($json['data']['isActive']);
    }

    public function testUpdateUserDuplicateEmail409(): void
    {
        $this->loginAs('admin');

        // Create two users
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'User A',
            'email' => 'usera@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);
        $userA = $this->getJsonResponse();

        $this->jsonRequest('POST', '/api/users', [
            'name' => 'User B',
            'email' => 'userb@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);
        $userB = $this->getJsonResponse();

        // Try to update User B's email to User A's email
        $this->jsonRequest('PUT', '/api/users/' . $userB['data']['id'], [
            'name' => 'User B',
            'email' => 'usera@smartpay.uz',
            'role' => 'user',
            'is_active' => true,
        ]);

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public function testDeleteUserAsAdmin(): void
    {
        $this->loginAs('admin');

        // Create user to delete
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'To Delete',
            'email' => 'delete@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);
        $created = $this->getJsonResponse();
        $userId = $created['data']['id'];

        // Delete
        $this->jsonRequest('DELETE', '/api/users/' . $userId);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Verify user is soft-deleted (not visible in list)
        $this->jsonRequest('GET', '/api/users/' . $userId);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testDeleteUserAsUserForbidden(): void
    {
        $this->loginAs('user');

        $this->jsonRequest('DELETE', '/api/users/1');

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testDeleteUserRevokesRefreshTokens(): void
    {
        $admin = $this->loginAs('admin');

        // Create user
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'Token User',
            'email' => 'tokenuser@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);
        $created = $this->getJsonResponse();
        $userId = $created['data']['id'];

        // Issue a refresh token for the user
        $user = $this->em->getRepository(User::class)->find($userId);
        $refreshTokenStore = self::getContainer()->get(\App\Service\Auth\RefreshTokenStore::class);
        $refreshTokenStore->issue($user, 'TestAgent', '127.0.0.1');

        // Verify token exists and is not revoked
        $tokens = $this->em->getRepository(RefreshToken::class)->findBy(['user' => $user]);
        self::assertNotEmpty($tokens);
        self::assertNull($tokens[0]->getRevokedAt());

        // Delete the user
        $this->jsonRequest('DELETE', '/api/users/' . $userId);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Check refresh tokens are revoked
        $this->em->clear();
        $tokens = $this->em->getRepository(RefreshToken::class)->findBy(['user' => $userId]);
        foreach ($tokens as $token) {
            self::assertNotNull($token->getRevokedAt());
        }
    }

    // ─── RESET PASSWORD ─────────────────────────────────────────────

    public function testResetPasswordServerGenerated(): void
    {
        $this->loginAs('admin');

        // Create user
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'Reset User',
            'email' => 'reset@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);
        $created = $this->getJsonResponse();
        $userId = $created['data']['id'];

        // Reset password without providing new_password
        $this->jsonRequest('POST', '/api/users/' . $userId . '/reset-password', []);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $json = $this->getJsonResponse();
        self::assertArrayHasKey('new_password', $json);
        $newPassword = $json['new_password'];

        // Password should be at least 12 chars with mixed case and digit
        self::assertGreaterThanOrEqual(12, strlen($newPassword));
        self::assertMatchesRegularExpression('/[A-Z]/', $newPassword);
        self::assertMatchesRegularExpression('/[a-z]/', $newPassword);
        self::assertMatchesRegularExpression('/[0-9]/', $newPassword);
    }

    public function testResetPasswordClientProvided(): void
    {
        $this->loginAs('admin');

        // Create user
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'Reset User 2',
            'email' => 'reset2@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);
        $created = $this->getJsonResponse();
        $userId = $created['data']['id'];

        // Reset password with new_password provided
        $this->jsonRequest('POST', '/api/users/' . $userId . '/reset-password', [
            'new_password' => 'NewStrongPass456!',
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $json = $this->getJsonResponse();
        self::assertSame('NewStrongPass456!', $json['new_password']);
    }

    public function testResetPasswordAsUserForbidden(): void
    {
        $this->loginAs('user');

        $this->jsonRequest('POST', '/api/users/1/reset-password', []);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testResetPasswordInvalidShort(): void
    {
        $this->loginAs('admin');

        // Create user
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'Reset User 3',
            'email' => 'reset3@smartpay.uz',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);
        $created = $this->getJsonResponse();
        $userId = $created['data']['id'];

        // Reset password with too short password
        $this->jsonRequest('POST', '/api/users/' . $userId . '/reset-password', [
            'new_password' => 'short',
        ]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
    }
}
