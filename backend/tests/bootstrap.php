<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Enforce server timezone for the entire test suite. Some property tests
// rely on `\DateTimeImmutable('today')` returning a date in Asia/Tashkent.
date_default_timezone_set($_SERVER['APP_TIMEZONE'] ?? 'Asia/Tashkent');

// Ensure the test database schema is created/reset.
// We use Doctrine SchemaTool to create the schema from entity metadata.
// Individual test cases (PropertyTestCase, AbstractApiTestCase) also reset
// per-test, but this bootstrap ensures the DB exists on first run.
if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'test') {
    $kernel = new \App\Kernel('test', true);
    $kernel->boot();

    $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
    $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
    $metadata = $em->getMetadataFactory()->getAllMetadata();

    if (!empty($metadata)) {
        // Drop and recreate to ensure clean state
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    $kernel->shutdown();
    unset($kernel, $em, $schemaTool, $metadata);
}
