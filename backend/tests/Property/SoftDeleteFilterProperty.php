<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\UserRepository;

/**
 * Property 38: Soft Delete Filter Property
 *
 * Random clients/users/invoices in soft-deleted state — default queries
 * never return rows where `deleted_at IS NOT NULL`.
 *
 * **Validates: Requirements 11.6**
 */
class SoftDeleteFilterProperty extends PropertyTestCase
{
    private ClientRepository $clientRepo;
    private UserRepository $userRepo;
    private InvoiceRepository $invoiceRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientRepo = self::getContainer()->get(ClientRepository::class);
        $this->userRepo = self::getContainer()->get(UserRepository::class);
        $this->invoiceRepo = self::getContainer()->get(InvoiceRepository::class);
    }

    /**
     * Property: Default queries on clients never return soft-deleted records.
     */
    public function testSoftDeletedClientsNotInDefaultQuery(): void
    {
        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            $this->resetDatabaseForIteration();

            $aliveCount = $this->randomInt(0, 5);
            $deletedCount = $this->randomInt(1, 5);

            // Create alive clients
            for ($j = 0; $j < $aliveCount; $j++) {
                $client = Generators::randomClient();
                $client->setInn(Generators::randomInn()); // ensure unique
                $this->em->persist($client);
            }

            // Create soft-deleted clients
            for ($j = 0; $j < $deletedCount; $j++) {
                $client = Generators::randomClient();
                $client->setInn(Generators::randomInn());
                $client->setDeletedAt(new \DateTimeImmutable('-' . $this->randomInt(1, 365) . ' days'));
                $this->em->persist($client);
            }

            $this->em->flush();
            $this->em->clear();

            // Default query should only return alive clients
            $results = $this->clientRepo->findAll();

            $this->assertCount($aliveCount, $results, sprintf(
                'Iteration %d: Expected %d alive clients, got %d (deleted=%d)',
                $i, $aliveCount, count($results), $deletedCount
            ));

            // Verify none of the results have deleted_at set
            foreach ($results as $client) {
                $this->assertNull(
                    $client->getDeletedAt(),
                    'Default query returned a soft-deleted client'
                );
            }
        }
    }

    /**
     * Property: Default queries on users never return soft-deleted records.
     */
    public function testSoftDeletedUsersNotInDefaultQuery(): void
    {
        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            $this->resetDatabaseForIteration();

            $aliveCount = $this->randomInt(0, 5);
            $deletedCount = $this->randomInt(1, 5);

            // Create alive users
            for ($j = 0; $j < $aliveCount; $j++) {
                $user = $this->createRandomUser();
                $this->em->persist($user);
            }

            // Create soft-deleted users
            for ($j = 0; $j < $deletedCount; $j++) {
                $user = $this->createRandomUser();
                $user->setDeletedAt(new \DateTimeImmutable('-' . $this->randomInt(1, 365) . ' days'));
                $this->em->persist($user);
            }

            $this->em->flush();
            $this->em->clear();

            $results = $this->userRepo->findAll();

            $this->assertCount($aliveCount, $results, sprintf(
                'Iteration %d: Expected %d alive users, got %d (deleted=%d)',
                $i, $aliveCount, count($results), $deletedCount
            ));

            foreach ($results as $user) {
                $this->assertNull(
                    $user->getDeletedAt(),
                    'Default query returned a soft-deleted user'
                );
            }
        }
    }

    /**
     * Property: Default queries on invoices never return soft-deleted records.
     */
    public function testSoftDeletedInvoicesNotInDefaultQuery(): void
    {
        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            $this->resetDatabaseForIteration();

            $aliveCount = $this->randomInt(0, 5);
            $deletedCount = $this->randomInt(1, 5);

            // Create alive invoices
            for ($j = 0; $j < $aliveCount; $j++) {
                $invoice = $this->createRandomInvoice($j + 1);
                $this->em->persist($invoice);
            }

            // Create soft-deleted invoices
            for ($j = 0; $j < $deletedCount; $j++) {
                $invoice = $this->createRandomInvoice($aliveCount + $j + 1);
                $invoice->setDeletedAt(new \DateTimeImmutable('-' . $this->randomInt(1, 365) . ' days'));
                $this->em->persist($invoice);
            }

            $this->em->flush();
            $this->em->clear();

            $results = $this->invoiceRepo->findAll();

            $this->assertCount($aliveCount, $results, sprintf(
                'Iteration %d: Expected %d alive invoices, got %d (deleted=%d)',
                $i, $aliveCount, count($results), $deletedCount
            ));

            foreach ($results as $invoice) {
                $this->assertNull(
                    $invoice->getDeletedAt(),
                    'Default query returned a soft-deleted invoice'
                );
            }
        }
    }

    /**
     * Property: findIncludingDeleted() returns both alive and soft-deleted records.
     */
    public function testFindIncludingDeletedReturnsAll(): void
    {
        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            $this->resetDatabaseForIteration();

            $aliveCount = $this->randomInt(0, 5);
            $deletedCount = $this->randomInt(1, 5);
            $totalExpected = $aliveCount + $deletedCount;

            for ($j = 0; $j < $aliveCount; $j++) {
                $client = Generators::randomClient();
                $client->setInn(Generators::randomInn());
                $this->em->persist($client);
            }

            for ($j = 0; $j < $deletedCount; $j++) {
                $client = Generators::randomClient();
                $client->setInn(Generators::randomInn());
                $client->setDeletedAt(new \DateTimeImmutable('-' . $this->randomInt(1, 365) . ' days'));
                $this->em->persist($client);
            }

            $this->em->flush();
            $this->em->clear();

            $allResults = $this->clientRepo->findIncludingDeleted();

            $this->assertCount($totalExpected, $allResults, sprintf(
                'Iteration %d: findIncludingDeleted() expected %d, got %d',
                $i, $totalExpected, count($allResults)
            ));
        }
    }

    // --- Helpers ---

    private function resetDatabaseForIteration(): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM invoice_items');
        $this->em->getConnection()->executeStatement('DELETE FROM invoices');
        $this->em->getConnection()->executeStatement('DELETE FROM clients');
        $this->em->getConnection()->executeStatement('DELETE FROM users');
        $this->em->clear();
    }

    private function createRandomUser(): User
    {
        $user = new User();
        $user->setName(Generators::randomName());
        $user->setEmail(sprintf('user_%s@test.com', bin2hex(random_bytes(4))));
        $user->setPasswordHash(password_hash('test123', PASSWORD_BCRYPT));
        $user->setRole(mt_rand(0, 1) === 0 ? UserRole::Admin : UserRole::User);

        return $user;
    }

    private function createRandomInvoice(int $serial): Invoice
    {
        $period = Generators::randomPeriod();
        $invoice = new Invoice();
        $invoice->setInvoiceNumber(sprintf('FAKTURA-O=%s-%03d', date('d.m.Y'), $serial));
        $invoice->setPeriod($period);
        $invoice->setSerialNo($serial);
        $invoice->setIssueDate(new \DateTimeImmutable());
        $invoice->setTotalAmount('0.00');
        $invoice->setItemsCount(0);
        $invoice->setUnitPriceSnapshot('100000.00');
        $invoice->setProductNameSnapshot('Test product');
        $invoice->setResponsibleName('Test');

        return $invoice;
    }
}
