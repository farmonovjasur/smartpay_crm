<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Writes audit log entries to the audit_logs table with IP and user-agent information.
 */
final class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * Log an audit event.
     *
     * @param User|null        $actor      The user performing the action (null for system actions)
     * @param string           $action     Action identifier (e.g., 'client.created')
     * @param string           $entityType Entity type name (e.g., 'client')
     * @param int|string|null  $entityId   The entity ID
     * @param array            $details    Additional details (stored as JSON)
     * @param Request|null     $req        The HTTP request (falls back to RequestStack if null)
     */
    public function log(
        ?User $actor,
        string $action,
        string $entityType,
        int|string|null $entityId = null,
        array $details = [],
        ?Request $req = null,
    ): void {
        $request = $req ?? $this->requestStack->getCurrentRequest();

        $auditLog = new AuditLog();
        $auditLog->setUser($actor);
        $auditLog->setAction($action);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId !== null ? (string) $entityId : null);
        $auditLog->setDetails(!empty($details) ? $details : null);
        $auditLog->setIp($this->resolveFullIp($request));
        $auditLog->setUserAgent($request?->headers->get('User-Agent'));

        $this->em->persist($auditLog);
        $this->em->flush();
    }

    /**
     * Resolves the full client IP address, including X-Forwarded-For chain when behind a proxy.
     */
    private function resolveFullIp(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $clientIp = $request->getClientIp();
        $forwarded = $request->headers->get('X-Forwarded-For');

        if ($forwarded) {
            // X-Forwarded-For contains the full chain: "client, proxy1, proxy2"
            // getClientIp() returns the trusted client IP; we store both for traceability
            $forwardedClean = trim($forwarded);
            if ($forwardedClean !== $clientIp) {
                return $clientIp . ' (via ' . $forwardedClean . ')';
            }
        }

        return $clientIp;
    }
}
