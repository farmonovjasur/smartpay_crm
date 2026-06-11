<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    // Enforce server timezone (Asia/Tashkent) before kernel boot so that
    // any \DateTime/\DateTimeImmutable created during request lifecycle
    // (especially in cron handlers) uses the correct timezone.
    $tz = $context['APP_TIMEZONE'] ?? 'Asia/Tashkent';
    date_default_timezone_set($tz);

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
