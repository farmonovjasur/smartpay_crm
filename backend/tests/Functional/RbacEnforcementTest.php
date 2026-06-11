<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Enum\ClientStatus;
use App\Enum\PaymentType;

/**
 * Functional test: user role is denied access to admin-only endpoints.
 *
 * Verifies that user role gets 403 on:
 * - GET /api/users
 * - POST /api/users
 * - DELETE /api/clients/{id}
 * - DELETE /api/invoices/{id}
 * - GET /api/audit-logs
 */
class RbacEnforcementTest extends AbstractApiTestCase
{
    public function testUserCannotAccessUsersList(): void
    {
        $this->loginAs('user');

        $this->jsonRequest('GET', '/api/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function testUserCannotCreateUser(): void
    {
        $this->loginAs('user');

        $this->jsonRequest('POST', '/api/users', [
            'name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'SecurePass123!',
            'role' => 'user',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUserCannotDeleteClient(): void
    {
        $this->loginAs('user');

        $client = $this->createTestClient();

        $this->jsonRequest('DELETE', '/api/clients/' . $client->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testUserCannotDeleteInvoice(): void
    {
        $this->loginAs('user');

        $invoice = $this->createTestInvoice();

        $this->jsonRequest('DELETE', '/api/invoices/' . $invoice->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testUserCannotAccessAuditLogs(): void
    {
        $this->loginAs('user');

        $this->jsonRequest('GET', '/api/audit-logs');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessUsersList(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('GET', '/api/users');

        // Should not be 403 (could be 200 or 404 depending on controller existence)
        self::assertNotEquals(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCanAccessAuditLogs(): void
    {
        $this->loginAs('admin');

        $this->jsonRequest('GET', '/api/audit-logs');

        // Should not be 403
        self::assertNotEquals(403, $this->client->getResponse()->getStatusCode());
    }

    private function createTestClient(): Client
    {
        $client = new Client();
        $client->setInn('123456789');
        $client->setName('Test Client');
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
