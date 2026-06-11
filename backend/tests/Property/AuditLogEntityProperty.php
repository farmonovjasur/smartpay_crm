<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Entity\AuditLog;
use App\Entity\Client;
use App\Entity\User;
use App\Enum\ClientStatus;
use App\Enum\PaymentType;
use App\Enum\UserRole;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Property 36: Audit log entity records.
 *
 * For all create/update/soft-delete operations on tracked entities
 * (clients, invoices, debts, users, payments, client_monthly_status),
 * exactly one new audit_logs entry is created after each operation.
 *
 * **Validates: Requirements 10.1, 10.3**
 */
class AuditLogEntityProperty extends PropertyTestCase
{
    /**
     * @test
     */
    public function eachTrackedEntityOperationCreatesExactlyOneAuditEntry(): void
    {
        // We need the AuditListener to be active — it's a Doctrine event listener
        // Create a user to act as the actor (security token context)
        $user = $this->createAdminUser();

        // Set security token so AuditListener can detect the actor
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles(),
        );
        $tokenStorage->setToken($token);

        for ($i = 0; $i < self::MIN_ITERATIONS; $i++) {
            // Pick a random operation: create, update, or delete a client
            $operation = mt_rand(0, 2);

            $auditCountBefore = $this->getAuditLogCount();

            switch ($operation) {
                case 0: // Create
                    $client = $this->createRandomClient();
                    break;

                case 1: // Update
                    $client = $this->createRandomClient();
                    $auditCountBefore = $this->getAuditLogCount(); // reset after create
                    $client->setName('Updated ' . Generators::randomName());
                    $this->em->flush();
                    break;

                case 2: // Soft delete (remove)
                    $client = $this->createRandomClient();
                    $auditCountBefore = $this->getAuditLogCount(); // reset after create
                    $this->em->remove($client);
                    $this->em->flush();
                    break;
            }

            $auditCountAfter = $this->getAuditLogCount();
            $newEntries = $auditCountAfter - $auditCountBefore;

            self::assertSame(
                1,
                $newEntries,
                sprintf(
                    'Iteration %d (op=%d): Expected exactly 1 new audit log entry, got %d',
                    $i,
                    $operation,
                    $newEntries,
                ),
            );
        }
    }

    private function createAdminUser(): User
    {
        $user = new User();
        $user->setName('Admin');
        $user->setEmail('admin_audit_' . uniqid() . '@test.smartpay.uz');

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, 'TestPassword123!'));
        $user->setRole(UserRole::Admin);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createRandomClient(): Client
    {
        $client = Generators::randomClient();
        $this->em->persist($client);
        $this->em->flush();

        return $client;
    }

    private function getAuditLogCount(): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(a.id) FROM ' . AuditLog::class . ' a'
        )->getSingleScalarResult();
    }
}
