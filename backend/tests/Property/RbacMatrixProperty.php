<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\ClientStatus;
use App\Enum\PaymentType;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Property 18: RBAC enforcement matrix.
 *
 * For all (role, endpoint) combinations, the response matches the RBAC matrix:
 * - user role on admin-only endpoints always gets 403
 * - admin role always gets access (not 403)
 *
 * **Validates: Requirements 2.2**
 */
class RbacMatrixProperty extends WebTestCase
{
    private const MIN_ITERATIONS = 100;

    private KernelBrowser $httpClient;
    private EntityManagerInterface $em;

    /**
     * RBAC matrix definition.
     * true = allowed, false = denied (403)
     */
    private const RBAC_MATRIX = [
        // Admin-only endpoints
        ['method' => 'GET', 'uri' => '/api/users', 'admin' => true, 'user' => false],
        ['method' => 'POST', 'uri' => '/api/users', 'admin' => true, 'user' => false],
        ['method' => 'DELETE', 'uri' => '/api/clients/{clientId}', 'admin' => true, 'user' => false],
        ['method' => 'DELETE', 'uri' => '/api/invoices/{invoiceId}', 'admin' => true, 'user' => false],
        ['method' => 'GET', 'uri' => '/api/audit-logs', 'admin' => true, 'user' => false],
        // Shared endpoints (admin + user)
        ['method' => 'GET', 'uri' => '/api/clients', 'admin' => true, 'user' => true],
        ['method' => 'POST', 'uri' => '/api/clients', 'admin' => true, 'user' => true],
        ['method' => 'GET', 'uri' => '/api/invoices', 'admin' => true, 'user' => true],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = static::createClient();
        $this->httpClient->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // ClientService.create() reads unit_price config when reconciling overdue months.
        $config = new \App\Entity\Config();
        $config->setConfigKey('unit_price');
        $config->setConfigValue('100000');
        $this->em->persist($config);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->httpClient, $this->em);
    }

    /**
     * @test
     */
    public function rbacMatrixIsEnforced(): void
    {
        $seed = (int) ($_SERVER['PBT_SEED'] ?? random_int(0, PHP_INT_MAX));
        mt_srand($seed);
        fwrite(STDERR, sprintf("[PBT] %s seed=%d\n", static::class, $seed));

        // Create test fixtures
        $admin = $this->createUser('admin');
        $user = $this->createUser('user');
        $client = $this->createTestClient();
        $invoice = $this->createTestInvoice();

        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            // Pick a random RBAC rule
            $rule = self::RBAC_MATRIX[mt_rand(0, count(self::RBAC_MATRIX) - 1)];

            // Pick a random role
            $role = mt_rand(0, 1) === 0 ? 'admin' : 'user';
            $expectedAllowed = $rule[$role];
            $actor = $role === 'admin' ? $admin : $user;

            // Resolve URI placeholders
            $uri = str_replace(
                ['{clientId}', '{invoiceId}'],
                [(string) $client->getId(), (string) $invoice->getId()],
                $rule['uri'],
            );

            // Login as the selected role
            $this->httpClient->loginUser($actor, 'api');
            $csrfToken = bin2hex(random_bytes(16));
            $this->httpClient->getCookieJar()->set(
                new Cookie('csrf_token', $csrfToken, (string) (time() + 900), '/')
            );

            // Make request
            $serverHeaders = [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ];
            if (!in_array($rule['method'], ['GET', 'HEAD', 'OPTIONS'], true)) {
                $serverHeaders['HTTP_X_CSRF_TOKEN'] = $csrfToken;
            }

            $body = null;
            if ($rule['method'] === 'POST' && $rule['uri'] === '/api/users') {
                $body = json_encode([
                    'name' => 'Test',
                    'email' => 'rand' . $i . '@test.com',
                    'password' => 'Pass123!',
                    'role' => 'user',
                ]);
            } elseif ($rule['method'] === 'POST' && $rule['uri'] === '/api/clients') {
                $currentPeriod = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))->format('Y-m');
                $body = json_encode([
                    'inn' => str_pad((string) mt_rand(100000000, 999999999), 9, '0'),
                    'name' => 'Test Client',
                    'phone' => '+998901234567',
                    'service_date' => $currentPeriod . '-15',
                    'payment_type' => 'fakt',
                    'product_count' => 1,
                    'last_paid_period' => $currentPeriod,
                ]);
            }

            $this->httpClient->request($rule['method'], $uri, [], [], $serverHeaders, $body);
            $statusCode = $this->httpClient->getResponse()->getStatusCode();

            if (!$expectedAllowed) {
                // Should be 403
                self::assertSame(
                    403,
                    $statusCode,
                    sprintf(
                        'Iteration %d: %s %s as %s should be 403, got %d',
                        $i,
                        $rule['method'],
                        $uri,
                        $role,
                        $statusCode,
                    ),
                );
            } else {
                // Should NOT be 403
                self::assertNotSame(
                    403,
                    $statusCode,
                    sprintf(
                        'Iteration %d: %s %s as %s should NOT be 403, got %d',
                        $i,
                        $rule['method'],
                        $uri,
                        $role,
                        $statusCode,
                    ),
                );
            }
        }
    }

    private function createUser(string $role): User
    {
        $user = new User();
        $user->setName('Test ' . ucfirst($role));
        $user->setEmail($role . '@test.smartpay.uz');

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, 'TestPassword123!'));
        $user->setRole(UserRole::from($role));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createTestClient(): Client
    {
        $client = new Client();
        $client->setInn('123456789');
        $client->setName('RBAC Test Client');
        $client->setPhone('+998901234567');
        $client->setServiceDate(new \DateTimeImmutable('2025-01-15'));
        $client->setPaymentType(PaymentType::Fakt);
        $client->setProductCount(1);
        $client->setStatus(ClientStatus::Faol);

        $this->em->persist($client);
        $this->em->flush();

        return $client;
    }

    private function createTestInvoice(): Invoice
    {
        $invoice = new Invoice();
        $invoice->setInvoiceNumber('FAKTURA-O=15.01.2025-001');
        $invoice->setPeriod('2025-01');
        $invoice->setSerialNo(1);
        $invoice->setIssueDate(new \DateTimeImmutable('2025-01-15'));
        $invoice->setTotalAmount('0.00');
        $invoice->setItemsCount(0);
        $invoice->setResponsibleName('Test');
        $invoice->setUnitPriceSnapshot('100000.00');
        $invoice->setProductNameSnapshot('Test product');

        $this->em->persist($invoice);
        $this->em->flush();

        return $invoice;
    }
}
