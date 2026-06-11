<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\Client;
use App\Entity\ClientMonthlyStatus;
use App\Entity\Debt;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Service\Audit\AuditLogger;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Doctrine event listener that automatically logs audit entries
 * for postPersist/postUpdate/postRemove events on tracked entities.
 *
 * Tracked entities: Client, Invoice, Debt, User, Payment, ClientMonthlyStatus
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class AuditListener
{
    private const TRACKED_ENTITIES = [
        Client::class => 'client',
        Invoice::class => 'invoice',
        Debt::class => 'debt',
        User::class => 'user',
        Payment::class => 'payment',
        ClientMonthlyStatus::class => 'client_monthly_status',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->handleEvent($args->getObject(), 'created');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->handleEvent($args->getObject(), 'updated');
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->handleEvent($args->getObject(), 'deleted');
    }

    private function handleEvent(object $entity, string $action): void
    {
        // Skip AuditLog entities to prevent recursion
        if ($entity instanceof AuditLog) {
            return;
        }

        $entityClass = get_class($entity);
        $entityType = self::TRACKED_ENTITIES[$entityClass] ?? null;

        if ($entityType === null) {
            return;
        }

        $actor = $this->getCurrentUser();
        $entityId = $this->getEntityId($entity);

        $this->auditLogger->log(
            actor: $actor,
            action: "{$entityType}.{$action}",
            entityType: $entityType,
            entityId: $entityId,
            details: $this->buildDetails($entity, $action),
        );
    }

    private function getCurrentUser(): ?User
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof User ? $user : null;
    }

    private function getEntityId(object $entity): int|string|null
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }

        return null;
    }

    private function buildDetails(object $entity, string $action): array
    {
        $details = [];

        // For created/updated, include basic identifying information
        if ($entity instanceof Client) {
            $details['name'] = $entity->getName();
            $details['inn'] = $entity->getInn();
        } elseif ($entity instanceof Invoice && method_exists($entity, 'getInvoiceNumber')) {
            $details['invoice_number'] = $entity->getInvoiceNumber();
            if (method_exists($entity, 'getPeriod')) {
                $details['period'] = $entity->getPeriod();
            }
        } elseif ($entity instanceof User) {
            $details['email'] = $entity->getEmail();
            $details['role'] = $entity->getRole()->value;
        } elseif ($entity instanceof Debt && method_exists($entity, 'getStatus')) {
            $details['status'] = $entity->getStatus()->value;
            if (method_exists($entity, 'getAmount')) {
                $details['amount'] = $entity->getAmount();
            }
        } elseif ($entity instanceof Payment) {
            if (method_exists($entity, 'getAmount')) {
                $details['amount'] = $entity->getAmount();
            }
            if (method_exists($entity, 'getPeriod')) {
                $details['period'] = $entity->getPeriod();
            }
        }

        return $details;
    }
}
