<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Message\ReconcileDebtsAsync;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Self-healing debt reconciliation fallback.
 *
 * On the first /api/* request of each calendar day, dispatches an async
 * message to reconcile debts IF the daily cron has not already done so.
 *
 * Key design decisions:
 * - Uses Symfony Lock (DB-based via LOCK_DSN) instead of file markers — no
 *   race conditions, works across multiple app servers.
 * - Dispatches async message — zero latency impact on the triggering request.
 * - The actual reconciliation handler also acquires a lock, so even if both
 *   the scheduler AND this listener dispatch messages, the work runs once.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -100)]
final class DailyDebtReconcileListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Skip health check endpoint to avoid infinite self-triggering
        if (str_starts_with($path, '/api/health')) {
            return;
        }

        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))->format('Y-m-d');

        // Use a short-lived lock to check if we've already dispatched today.
        // This lock is separate from the handler's lock — its purpose is to
        // prevent dispatching the message more than once per day.
        $dispatchLock = $this->lockFactory->createLock(
            'debt_reconcile_dispatch_' . $today,
            ttl: 86400, // 24 hours — auto-expires next day
        );

        // Non-blocking: if already dispatched today, skip immediately
        if (!$dispatchLock->acquire(blocking: false)) {
            return;
        }

        // Dispatch async — the handler will do the actual heavy lifting
        try {
            $this->messageBus->dispatch(new ReconcileDebtsAsync($today));

            $this->logger->info('DailyDebtReconcileListener: dispatched async reconciliation', [
                'date' => $today,
            ]);
        } catch (\Throwable $e) {
            // Release lock so next request can retry
            $dispatchLock->release();

            $this->logger->error('DailyDebtReconcileListener: dispatch failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
