<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\Client;
use App\Entity\User;
use App\Security\Voter\AuditLogVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/audit-logs', name: 'audit_log_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AuditLogVoter::VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', '20')));

        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.id', 'DESC');

        if ($entityType = $request->query->get('entity_type')) {
            $qb->andWhere('a.entityType = :et')->setParameter('et', $entityType);
        }
        if ($userId = $request->query->get('user_id')) {
            $qb->andWhere('a.user = :uid')->setParameter('uid', (int) $userId);
        }
        if ($from = $request->query->get('from')) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', new \DateTimeImmutable($from));
        }
        if ($to = $request->query->get('to')) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', new \DateTimeImmutable($to));
        }

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $logs = $qb->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (AuditLog $l) => [
            'id' => $l->getId(),
            'user_id' => $l->getUser()?->getId(),
            'user_name' => $l->getUser()?->getName(),
            'user_email' => $l->getUser()?->getEmail(),
            'action' => $l->getAction(),
            'entity_type' => $l->getEntityType(),
            'entity_id' => $l->getEntityId(),
            'entity_label' => $this->resolveEntityLabel($l),
            'details' => $l->getDetails(),
            'ip' => $l->getIp(),
            'user_agent' => $l->getUserAgent(),
            'created_at' => $l->getCreatedAt()->format('c'),
        ], $logs);

        return new JsonResponse(['data' => $data, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
    }

    /**
     * Resolves a human-readable label for the entity referenced by an audit log.
     * Falls back to details stored at log time if the entity no longer exists.
     */
    private function resolveEntityLabel(AuditLog $log): ?string
    {
        $entityId = $log->getEntityId();
        if ($entityId === null) {
            return null;
        }

        $details = $log->getDetails();

        // Try to resolve live entity name
        switch ($log->getEntityType()) {
            case 'client':
                $client = $this->em->find(Client::class, (int) $entityId);
                if ($client) {
                    return $client->getName() . ' (INN: ' . $client->getInn() . ')';
                }
                // Fallback to saved details
                if (!empty($details['name'])) {
                    return $details['name'] . (!empty($details['inn']) ? ' (INN: ' . $details['inn'] . ')' : '');
                }
                break;

            case 'user':
                $user = $this->em->find(User::class, (int) $entityId);
                if ($user) {
                    return $user->getName() . ' (' . $user->getEmail() . ')';
                }
                if (!empty($details['email'])) {
                    return $details['email'];
                }
                break;

            case 'invoice':
                if (!empty($details['invoice_number'])) {
                    $label = $details['invoice_number'];
                    if (!empty($details['period'])) {
                        $label .= ' — ' . $details['period'];
                    }
                    return $label;
                }
                break;

            case 'debt':
                if (!empty($details['amount'])) {
                    return 'Summa: ' . number_format((float) $details['amount'], 0, '.', ' ');
                }
                break;

            case 'payment':
                if (!empty($details['amount'])) {
                    $label = 'Summa: ' . number_format((float) $details['amount'], 0, '.', ' ');
                    if (!empty($details['period'])) {
                        $label .= ' — ' . $details['period'];
                    }
                    return $label;
                }
                break;
        }

        return null;
    }
}
