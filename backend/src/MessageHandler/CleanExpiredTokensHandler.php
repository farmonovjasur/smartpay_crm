<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CleanExpiredTokens;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CleanExpiredTokensHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(CleanExpiredTokens $message): void
    {
        $this->em->getConnection()->executeStatement(
            'DELETE FROM refresh_tokens WHERE expires_at < NOW()'
        );
    }
}
