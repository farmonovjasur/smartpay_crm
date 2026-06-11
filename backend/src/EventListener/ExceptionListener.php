<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -10)]
final class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $headers = $exception->getHeaders();
        } else {
            $statusCode = 500;
            $headers = [];
        }

        $body = [
            'error' => $this->getErrorType($statusCode),
            'message' => $exception->getMessage(),
            'code' => $statusCode,
        ];

        if (isset($headers['Retry-After'])) {
            $body['retry_after'] = (int) $headers['Retry-After'];
        }

        $response = new JsonResponse($body, $statusCode, $headers);
        $event->setResponse($response);
    }

    private function getErrorType(int $code): string
    {
        return match ($code) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            413 => 'payload_too_large',
            422 => 'unprocessable_entity',
            429 => 'rate_limit_exceeded',
            default => 'internal_error',
        };
    }
}
