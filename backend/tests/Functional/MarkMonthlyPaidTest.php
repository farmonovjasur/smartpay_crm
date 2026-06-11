<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\Config;
use App\Enum\ClientStatus;
use App\Enum\PaymentType;

final class MarkMonthlyPaidTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed config
        $config = new Config();
        $config->setConfigKey('unit_price');
        $config->setConfigValue('100000');
        $this->em->persist($config);
        $this->em->flush();
    }

    private function makeClient(): Client
    {
        $client = new Client();
        $client->setInn('123456789');
        $client->setName('Test Client');
        $client->setPhone('+998901234567');
        $client->setServiceDate(new \DateTimeImmutable('2025-01-15'));
        $client->setPaymentType(PaymentType::Naqt);
        $client->setProductCount(3);
        $client->setStatus(ClientStatus::Faol);
        $this->em->persist($client);
        $this->em->flush();

        return $client;
    }

    public function testMarkPaidSuccessful(): void
    {
        $this->loginAs('admin');
        $client = $this->makeClient();

        $this->jsonRequest('POST', '/api/clients/' . $client->getId() . '/mark-monthly-paid', [
            'period' => '2025-06',
            'method' => 'naqt',
        ]);

        self::assertResponseStatusCodeSame(200);
        $json = $this->getJsonResponse();
        self::assertEquals('paid', $json['data']['status']);
        self::assertEquals('naqt', $json['data']['method']);
        self::assertEquals('2025-06', $json['data']['period']);
    }

    public function testMarkPaidDuplicate409(): void
    {
        $this->loginAs('admin');
        $client = $this->makeClient();

        $this->jsonRequest('POST', '/api/clients/' . $client->getId() . '/mark-monthly-paid', [
            'period' => '2025-06',
            'method' => 'naqt',
        ]);
        self::assertResponseStatusCodeSame(200);

        $this->jsonRequest('POST', '/api/clients/' . $client->getId() . '/mark-monthly-paid', [
            'period' => '2025-06',
            'method' => 'naqt',
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testMarkPaidValidationErrors(): void
    {
        $this->loginAs('admin');
        $client = $this->makeClient();

        $this->jsonRequest('POST', '/api/clients/' . $client->getId() . '/mark-monthly-paid', [
            'period' => 'invalid',
            'method' => 'wrong',
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testMarkPaidUserRoleAllowed(): void
    {
        $this->loginAs('user');
        $client = $this->makeClient();

        $this->jsonRequest('POST', '/api/clients/' . $client->getId() . '/mark-monthly-paid', [
            'period' => '2025-06',
            'method' => 'fakt',
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    public function testMarkPaidClientNotFound(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients/9999/mark-monthly-paid', [
            'period' => '2025-06',
            'method' => 'naqt',
        ]);
        self::assertResponseStatusCodeSame(404);
    }
}
