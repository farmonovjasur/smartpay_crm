<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Property 14: Random state-changing so'rovlar — header cookie bilan mos kelmasa har doim 403.
 *
 * For any state-changing (POST/PUT/PATCH/DELETE) request to /api/* endpoints
 * (excluding /api/auth/login and /api/auth/refresh), if the X-CSRF-Token header
 * does not match the csrf_token cookie, the response is always 403.
 *
 * **Validates: Requirements 1.5**
 */
class CsrfDoubleSubmitProperty extends WebTestCase
{
    private const MIN_ITERATIONS = 100;

    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const TEST_ENDPOINTS = [
        '/api/auth/logout',
        '/api/auth/me',     // GET is safe but we test POST to it
        '/api/clients',
        '/api/clients/1',
    ];

    private KernelBrowser $httpClient;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testMismatchedCsrfAlwaysReturns403(): void
    {
        // Seed RNG
        $seed = (int) ($_SERVER['PBT_SEED'] ?? random_int(0, PHP_INT_MAX));
        mt_srand($seed);
        fwrite(STDERR, sprintf("[PBT] %s seed=%d\n", static::class, $seed));

        // Create and login a user for authenticated requests
        $user = $this->createAndLoginUser();

        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            $method = self::STATE_CHANGING_METHODS[mt_rand(0, count(self::STATE_CHANGING_METHODS) - 1)];
            $endpoint = self::TEST_ENDPOINTS[mt_rand(0, count(self::TEST_ENDPOINTS) - 1)];

            // Generate two different random tokens (cookie vs header mismatch)
            $cookieToken = bin2hex(random_bytes(16));
            $headerToken = bin2hex(random_bytes(16));

            // Ensure they are actually different
            while ($headerToken === $cookieToken) {
                $headerToken = bin2hex(random_bytes(16));
            }

            $this->httpClient->getCookieJar()->set(
                new \Symfony\Component\BrowserKit\Cookie('csrf_token', $cookieToken, (string) (time() + 900), '/')
            );

            $this->httpClient->request($method, $endpoint, [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $headerToken,
            ], '{}');

            $statusCode = $this->httpClient->getResponse()->getStatusCode();

            self::assertSame(
                Response::HTTP_FORBIDDEN,
                $statusCode,
                "Expected 403 for CSRF mismatch: $method $endpoint (iter=$i, cookie=$cookieToken, header=$headerToken, got=$statusCode)"
            );
        }
    }

    public function testMissingCsrfHeaderAlwaysReturns403(): void
    {
        $seed = (int) ($_SERVER['PBT_SEED'] ?? random_int(0, PHP_INT_MAX));
        mt_srand($seed);

        $this->createAndLoginUser();

        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            $method = self::STATE_CHANGING_METHODS[mt_rand(0, count(self::STATE_CHANGING_METHODS) - 1)];
            $endpoint = self::TEST_ENDPOINTS[mt_rand(0, count(self::TEST_ENDPOINTS) - 1)];

            $cookieToken = bin2hex(random_bytes(16));

            $this->httpClient->getCookieJar()->set(
                new \Symfony\Component\BrowserKit\Cookie('csrf_token', $cookieToken, (string) (time() + 900), '/')
            );

            // No X-CSRF-Token header
            $this->httpClient->request($method, $endpoint, [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], '{}');

            self::assertSame(
                Response::HTTP_FORBIDDEN,
                $this->httpClient->getResponse()->getStatusCode(),
                "Expected 403 when header is missing: $method $endpoint (iter=$i)"
            );
        }
    }

    private function createAndLoginUser(): User
    {
        $user = new User();
        $user->setName('CSRF Prop User');
        $user->setEmail('csrf_prop_' . uniqid() . '@test.uz');
        $user->setRole(UserRole::Admin);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, 'TestPass123!'));

        $this->em->persist($user);
        $this->em->flush();

        $this->httpClient->loginUser($user);

        return $user;
    }
}
