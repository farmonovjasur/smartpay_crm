<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * Records a heartbeat timestamp every time the messenger worker processes
 * a message (or is idle). This lets the /api/health endpoint detect when
 * the worker has stopped.
 *
 * The heartbeat is stored in the `app_config` table as a simple key-value
 * row, updated at most once per minute to avoid excessive writes.
 */
#[AsEventListener(event: WorkerRunningEvent::class)]
final class WorkerHeartbeatListener
{
    private int $lastRecorded = 0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerRunningEvent $event): void
    {
        $now = time();

        // Throttle: write at most once per 60 seconds
        if ($now - $this->lastRecorded < 60) {
            return;
        }

        try {
            $this->em->getConnection()->executeStatement(
                "INSERT INTO app_config (`key`, `value`) VALUES ('worker_heartbeat', :ts)
                 ON DUPLICATE KEY UPDATE `value` = :ts",
                ['ts' => (string) $now],
            );
            $this->lastRecorded = $now;
        } catch (\Throwable $e) {
            $this->logger->warning('WorkerHeartbeatListener: failed to record heartbeat', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
