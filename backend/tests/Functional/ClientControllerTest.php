<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ClientControllerTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // ClientService.create() needs unit_price config to compute potential debt amounts.
        $config = new \App\Entity\Config();
        $config->setConfigKey('unit_price');
        $config->setConfigValue('100000');
        $this->em->persist($config);
        $this->em->flush();
    }

    private function validClientData(array $overrides = []): array
    {
        return array_merge([
            'inn' => '123456789',
            'name' => 'Test Client',
            'phone' => '+998901234567',
            'service_date' => '2025-06-15',
            'payment_type' => 'fakt',
            'product_count' => 2,
            'notes' => 'Test notes',
            'last_paid_period' => '2025-06',
        ], $overrides);
    }

    public function testListClients(): void
    {
        $this->loginAs('admin');

        // Create a client first
        $this->jsonRequest('POST', '/api/clients', $this->validClientData());
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('GET', '/api/clients');
        self::assertResponseStatusCodeSame(200);

        $json = $this->getJsonResponse();
        self::assertCount(1, $json['data']);
        self::assertEquals(1, $json['total']);
    }

    public function testCreateClient(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData());
        self::assertResponseStatusCodeSame(201);

        $json = $this->getJsonResponse();
        self::assertEquals('123456789', $json['data']['inn']);
        self::assertEquals('Test Client', $json['data']['name']);
        self::assertEquals('fakt', $json['data']['paymentType']);
    }

    public function testCreateClientInnDuplicate409(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData());
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/clients', $this->validClientData(['name' => 'Another']));
        self::assertResponseStatusCodeSame(409);
    }

    public function testCreateClientValidationErrors(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', ['inn' => '123', 'phone' => 'bad']);
        self::assertResponseStatusCodeSame(422);

        $json = $this->getJsonResponse();
        self::assertArrayHasKey('errors', $json);
    }

    public function testShowClient(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData());
        $created = $this->getJsonResponse();

        $this->jsonRequest('GET', '/api/clients/' . $created['data']['id']);
        self::assertResponseStatusCodeSame(200);
        self::assertEquals('123456789', $this->getJsonResponse()['data']['inn']);
    }

    public function testUpdateClient(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData());
        $id = $this->getJsonResponse()['data']['id'];

        $this->jsonRequest('PUT', '/api/clients/' . $id, $this->validClientData([
            'name' => 'Updated Name',
            'status' => 'nofaol',
        ]));
        self::assertResponseStatusCodeSame(200);

        $json = $this->getJsonResponse();
        self::assertEquals('Updated Name', $json['data']['name']);
        self::assertEquals('nofaol', $json['data']['status']);
    }

    public function testDeleteClientAdminOnly(): void
    {
        $this->loginAs('admin');
        $this->jsonRequest('POST', '/api/clients', $this->validClientData());
        $id = $this->getJsonResponse()['data']['id'];

        $this->jsonRequest('DELETE', '/api/clients/' . $id);
        self::assertResponseStatusCodeSame(200);

        // Verify soft-deleted (not visible in list)
        $this->jsonRequest('GET', '/api/clients');
        self::assertEquals(0, $this->getJsonResponse()['total']);
    }

    public function testDeleteClientUserRoleForbidden(): void
    {
        // Create with admin
        $this->loginAs('admin');
        $this->jsonRequest('POST', '/api/clients', $this->validClientData());
        $id = $this->getJsonResponse()['data']['id'];

        // Try delete with user role
        $this->loginAs('user');
        $this->jsonRequest('DELETE', '/api/clients/' . $id);
        self::assertResponseStatusCodeSame(403);
    }

    public function testFilterByPaymentType(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData(['inn' => '111111111', 'payment_type' => 'fakt']));
        $this->jsonRequest('POST', '/api/clients', $this->validClientData(['inn' => '222222222', 'payment_type' => 'naqt']));

        $this->jsonRequest('GET', '/api/clients?payment_type=fakt');
        $json = $this->getJsonResponse();
        self::assertEquals(1, $json['total']);
        self::assertEquals('fakt', $json['data'][0]['paymentType']);
    }

    public function testSearchFilter(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData(['inn' => '111111111', 'name' => 'Alpha Corp']));
        $this->jsonRequest('POST', '/api/clients', $this->validClientData(['inn' => '222222222', 'name' => 'Beta LLC']));

        $this->jsonRequest('GET', '/api/clients?search=Alpha');
        self::assertEquals(1, $this->getJsonResponse()['total']);
    }

    public function testUserRoleCanCRUDExceptDelete(): void
    {
        $this->loginAs('user');

        $this->jsonRequest('POST', '/api/clients', $this->validClientData());
        self::assertResponseStatusCodeSame(201);

        $id = $this->getJsonResponse()['data']['id'];

        $this->jsonRequest('GET', '/api/clients');
        self::assertResponseStatusCodeSame(200);

        $this->jsonRequest('GET', '/api/clients/' . $id);
        self::assertResponseStatusCodeSame(200);

        $this->jsonRequest('PUT', '/api/clients/' . $id, $this->validClientData(['name' => 'X']));
        self::assertResponseStatusCodeSame(200);
    }
}
