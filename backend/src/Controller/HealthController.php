<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Health check endpoint for external monitoring (UptimeRobot, cron watchdogs, etc).
 *
 * Returns:
 * - 200 if all critical subsystems are healthy
 * - 503 if any subsystem is degraded
 *
 * This endpoint is PUBLIC (no auth required) so that monitoring services
 * can hit it without JWT tokens. See security.yaml for access control.
 */
final class HealthController extends AbstractController
{
    private const WORKER_STALE_THRESHOLD_SECONDS = 7200; // 2 hours

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // 1. Database connectivity
        try {
            $this->em->getConnection()->fetchOne('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => 'Connection failed'];
            $healthy = false;
        }

        // 2. Worker/scheduler heartbeat check
        try {
            $lastHeartbeat = $this->em->getConnection()->fetchOne(
                "SELECT value FROM app_config WHERE `key` = 'worker_heartbeat'"
            );

            if ($lastHeartbeat === false || $lastHeartbeat === null) {
                $checks['worker'] = [
                    'status' => 'warning',
                    'message' => 'No heartbeat recorded yet (worker may have never started)',
                ];
                // Don't mark unhealthy for first deployment — worker needs to start once
            } else {
                $lastBeat = (int) $lastHeartbeat;
                $elapsed = time() - $lastBeat;

                if ($elapsed > self::WORKER_STALE_THRESHOLD_SECONDS) {
                    $checks['worker'] = [
                        'status' => 'error',
                        'message' => sprintf(
                            'Worker heartbeat stale (%d minutes ago, threshold: %d minutes)',
                            (int) ($elapsed / 60),
                            (int) (self::WORKER_STALE_THRESHOLD_SECONDS / 60),
                        ),
                        'last_seen' => date('c', $lastBeat),
                    ];
                    $healthy = false;
                } else {
                    $checks['worker'] = [
                        'status' => 'ok',
                        'last_seen' => date('c', $lastBeat),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $checks['worker'] = ['status' => 'error', 'message' => $e->getMessage()];
            $healthy = false;
        }

        // 3. Daily debt reconciliation status
        try {
            $todayDate = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))->format('Y-m-d');
            $lastRun = $this->em->getConnection()->fetchOne(
                "SELECT value FROM app_config WHERE `key` = 'last_debt_check_date'"
            );

            if ($lastRun === $todayDate) {
                $checks['debt_reconcile'] = ['status' => 'ok', 'last_run' => $lastRun];
            } else {
                $checks['debt_reconcile'] = [
                    'status' => 'warning',
                    'message' => 'Debt check has not run today yet',
                    'last_run' => $lastRun ?: 'never',
                ];
                // Warning only — the self-healing listener will trigger it soon
            }
        } catch (\Throwable $e) {
            $checks['debt_reconcile'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        return new JsonResponse(
            [
                'status' => $healthy ? 'healthy' : 'degraded',
                'timestamp' => date('c'),
                'checks' => $checks,
            ],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
