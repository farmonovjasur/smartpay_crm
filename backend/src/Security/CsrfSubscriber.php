<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CsrfSubscriber implements EventSubscriberInterface
{
    private const SKIP_PATHS = [
        '/api/auth/login',
        '/api/auth/refresh',
    ];

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public static function getSubscribedEvents(): array
    {
        // High priority — run before controller resolution
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip safe (read-only) methods
        if (\in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        // Skip auth endpoints that don't yet have a CSRF token
        $path = $request->getPathInfo();
        foreach (self::SKIP_PATHS as $skipPath) {
            if ($path === $skipPath || str_starts_with($path, $skipPath . '/')) {
                return;
            }
        }

        // Only enforce for /api paths
        if (!str_starts_with($path, '/api')) {
            return;
        }

        $cookieToken = $request->cookies->get('csrf_token', '');
        $headerToken = $request->headers->get('X-CSRF-Token', '');

        if ($cookieToken === '' || $headerToken === '' || !hash_equals($cookieToken, $headerToken)) {
            $event->setResponse(new JsonResponse(
                ['error' => 'CSRF token mismatch.'],
                Response::HTTP_FORBIDDEN,
            ));
        }
    }
}
