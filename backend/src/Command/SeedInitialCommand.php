<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:initial',
    description: 'Seeds the initial admin user from INITIAL_ADMIN_PASSWORD env variable',
)]
class SeedInitialCommand extends Command
{
    private const ADMIN_EMAIL = 'admin@smartpay.uz';
    private const ADMIN_NAME = 'Admin';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $password = $_ENV['INITIAL_ADMIN_PASSWORD'] ?? $_SERVER['INITIAL_ADMIN_PASSWORD'] ?? null;

        if (empty($password)) {
            $io->error('INITIAL_ADMIN_PASSWORD environment variable is not set or empty.');
            return Command::FAILURE;
        }

        // Check if admin already exists
        $existing = $this->em->getRepository(User::class)->findOneBy([
            'email' => self::ADMIN_EMAIL,
        ]);

        if ($existing !== null) {
            $io->warning(sprintf('Admin user "%s" already exists (ID: %d). Skipping.', self::ADMIN_EMAIL, $existing->getId()));
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setName(self::ADMIN_NAME);
        $user->setEmail(self::ADMIN_EMAIL);
        $user->setRole(UserRole::Admin);
        $user->setIsActive(true);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Admin user "%s" created successfully (ID: %d).', self::ADMIN_EMAIL, $user->getId()));

        return Command::SUCCESS;
    }
}
