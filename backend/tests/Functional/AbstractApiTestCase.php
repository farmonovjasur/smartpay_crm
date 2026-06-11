<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for functional API tests.
 *
 * Provides:
 * - JWT cookie auth helper (loginAs)
 * - CSRF token reading helper
 * - DB schema reset before each test
 */
abstract class AbstractApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    private ?User $loggedInUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $this->resetDatabase();
        $this->resetRateLimiterStorage();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->client, $this->em);
    }

    /**
     * Reset the test database schema.
     */
    private function resetDatabase(): void
    {
        $connection = $this->em->getConnection();

        // Disable FK checks so we can drop tables in any order
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = $connection->fetchFirstColumn('SHOW TABLES');
        foreach ($tables as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);
    }

    /**
     * Clear the rate limiter cache pool between tests so that limiter state
     * (e.g. failed login attempts) does not leak across test cases.
     */
    private function resetRateLimiterStorage(): void
    {
        $container = self::getContainer();

        foreach (['cache.rate_limiter', 'cache.app'] as $poolId) {
            if ($container->has($poolId)) {
                $pool = $container->get($poolId);
                if ($pool instanceof \Psr\Cache\CacheItemPoolInterface) {
                    $pool->clear();
                }
            }
        }
    }

    /**
     * Create a user with the given role and authenticate the test client.
     *
     * Sets JWT access_token and csrf_token cookies on the client so
     * subsequent requests are authenticated.
     *
     * @param string $role 'admin' or 'user'
     * @return User The created and persisted user entity
     */
    protected function loginAs(string $role): User
    {
        $user = $this->createUser($role);
        $this->loggedInUser = $user;

        // Use Symfony's built-in loginUser which bypasses the actual JWT flow
        // but correctly sets security context for the test client
        $this->client->loginUser($user, 'api');

        // Set a CSRF token cookie for state-changing requests
        $csrfToken = bin2hex(random_bytes(16));
        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie('csrf_token', $csrfToken, (string) (time() + 900), '/')
        );

        return $user;
    }

    /**
     * Get the current CSRF token from the client's cookie jar.
     */
    protected function getCsrfToken(): ?string
    {
        $cookie = $this->client->getCookieJar()->get('csrf_token');

        return $cookie?->getValue();
    }

    /**
     * Make a JSON request with CSRF header automatically included.
     *
     * @param string $method HTTP method
     * @param string $uri    Request URI
     * @param array  $data   Request body (will be JSON-encoded)
     * @param array  $headers Additional headers
     */
    protected function jsonRequest(
        string $method,
        string $uri,
        array $data = [],
        array $headers = [],
    ): void {
        // Re-authenticate before each request to handle stateless firewall
        if ($this->loggedInUser !== null) {
            $this->client->loginUser($this->loggedInUser, 'api');
        }

        $csrfToken = $this->getCsrfToken();

        $serverHeaders = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($csrfToken && !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $serverHeaders['HTTP_X_CSRF_TOKEN'] = $csrfToken;
        }

        foreach ($headers as $key => $value) {
            $serverHeaders['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $content = $data !== [] ? json_encode($data, JSON_THROW_ON_ERROR) : null;

        $this->client->request($method, $uri, [], [], $serverHeaders, $content);
    }

    /**
     * Decode the JSON response body.
     *
     * @return array<string, mixed>
     */
    protected function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();

        return json_decode($content ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Create and persist a user with the given role.
     */
    private function createUser(string $role, ?string $email = null): User
    {
        $roleEnum = UserRole::from($role);

        $user = new User();
        $user->setName('Test ' . ucfirst($role));
        $user->setEmail($email ?? $role . '_' . uniqid() . '@test.smartpay.uz');

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, 'TestPassword123!'));
        $user->setRole($roleEnum);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
