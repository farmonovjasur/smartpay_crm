<?php

declare(strict_types=1);

namespace App\Service\Config;

use App\Entity\Config;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class ConfigService
{
    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function get(string $key): string
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $config = $this->em->find(Config::class, $key);

        if ($config === null) {
            throw new \RuntimeException(sprintf('Config key "%s" not found.', $key));
        }

        $this->cache[$key] = $config->getConfigValue();

        return $this->cache[$key];
    }

    public function set(string $key, string $value, ?User $actor): void
    {
        $config = $this->em->find(Config::class, $key);

        if ($config === null) {
            $config = new Config();
            $config->setConfigKey($key);
            $this->em->persist($config);
        }

        $config->setConfigValue($value);
        $config->setUpdatedAt(new \DateTimeImmutable());
        $config->setUpdatedBy($actor);

        $this->em->flush();

        $this->cache[$key] = $value;
    }
}
