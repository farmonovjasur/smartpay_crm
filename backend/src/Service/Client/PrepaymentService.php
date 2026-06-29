<?php

declare(strict_types=1);

namespace App\Service\Client;

use App\Entity\Client;
use App\Entity\Prepayment;
use App\Entity\User;
use App\Enum\PayMethod;
use App\Repository\ClientRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PrepaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
        private readonly ConfigService $configService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Deposit money into a client's balance (prepayment).
     */
    public function deposit(int $clientId, string $amount, PayMethod $method, ?string $notes, User $actor): Prepayment
    {
        $client = $this->clientRepository->find($clientId);
        if ($client === null) {
            throw new NotFoundHttpException('Client not found.');
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw new UnprocessableEntityHttpException("Summa musbat son bo'lishi kerak.");
        }

        // Create prepayment record
        $prepayment = new Prepayment();
        $prepayment->setClient($client);
        $prepayment->setAmount($amount);
        $prepayment->setMethod($method);
        $prepayment->setPaidAt(new \DateTimeImmutable());
        $prepayment->setNotes($notes);
        $prepayment->setCreatedBy($actor);

        $this->em->persist($prepayment);

        // Add to client balance
        $client->addBalance($amount);
        $client->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        // Audit log
        $unitPrice = $this->configService->get('unit_price');
        $monthlyAmount = bcmul($unitPrice, (string) $client->getProductCount(), 2);
        $estimatedMonths = $monthlyAmount !== '0.00'
            ? (int) bcdiv($client->getBalance(), $monthlyAmount, 0)
            : 0;

        $this->auditLogger->log($actor, 'client.prepayment', 'client', $clientId, [
            'amount' => $amount,
            'method' => $method->value,
            'new_balance' => $client->getBalance(),
            'estimated_months' => $estimatedMonths,
        ]);

        return $prepayment;
    }

    /**
     * Get prepayment history for a client.
     *
     * @return array<array<string, mixed>>
     */
    public function getHistory(int $clientId): array
    {
        $client = $this->clientRepository->find($clientId);
        if ($client === null) {
            throw new NotFoundHttpException('Client not found.');
        }

        $prepayments = $this->em->getRepository(Prepayment::class)->findBy(
            ['client' => $client],
            ['paidAt' => 'DESC']
        );

        return array_map(static fn (Prepayment $p) => [
            'id' => $p->getId(),
            'amount' => $p->getAmount(),
            'method' => $p->getMethod()->value,
            'paid_at' => $p->getPaidAt()->format('c'),
            'notes' => $p->getNotes(),
            'created_by' => $p->getCreatedBy()?->getName(),
            'created_at' => $p->getCreatedAt()->format('c'),
        ], $prepayments);
    }
}
