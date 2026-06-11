<?php

declare(strict_types=1);

namespace App\Tests\Property;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base class for property-based tests.
 *
 * Provides:
 * - DB schema reset before each test (clean slate)
 * - Minimum 100 iterations constant
 * - Random seed logging for reproducibility
 */
abstract class PropertyTestCase extends KernelTestCase
{
    protected const MIN_ITERATIONS = 100;

    protected EntityManagerInterface $em;

    private int $seed;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $this->resetDatabase();

        // Seed random number generator and log for reproducibility
        $this->seed = (int) ($_SERVER['PBT_SEED'] ?? random_int(0, PHP_INT_MAX));
        mt_srand($this->seed);

        fwrite(STDERR, sprintf(
            "[PBT] %s seed=%d\n",
            static::class,
            $this->seed,
        ));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Prevent memory leaks
        unset($this->em);
    }

    /**
     * Reset the test database schema — drop all tables and recreate from entity metadata.
     */
    private function resetDatabase(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * Get the current random seed (useful for logging in subclasses).
     */
    protected function getSeed(): int
    {
        return $this->seed;
    }

    /**
     * Generate a random integer using the seeded RNG.
     */
    protected function randomInt(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }
}
