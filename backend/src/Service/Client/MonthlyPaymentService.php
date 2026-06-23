<?php

declare(strict_types=1);

namespace App\Service\Client;

use App\Entity\Client;
use App\Entity\ClientMonthlyStatus;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PayMethod;
use App\Enum\PaymentStatus;
use App\Exception\AlreadyPaidException;
use App\Repository\ClientRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class MonthlyPaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientRepository $clientRepository,
        private readonly ConfigService $configService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function markPaid(int $clientId, string $period, PayMethod $method, User $actor): ClientMonthlyStatus
    {
        $client = $this->clientRepository->find($clientId);
        if ($client === null) {
            throw new NotFoundHttpException('Client not found.');
        }

        // Check if already paid
        $existing = $this->em->getRepository(ClientMonthlyStatus::class)->findOneBy([
            'client' => $client,
            'period' => $period,
        ]);

        if ($existing !== null && $existing->getPaymentStatus() === PaymentStatus::Paid) {
            throw new AlreadyPaidException($period);
        }

        $unitPrice = $this->configService->get('unit_price');
        $amount = bcmul($unitPrice, (string) $client->getProductCount(), 2);

        // Upsert CMS
        if ($existing !== null) {
            $cms = $existing;
        } else {
            $cms = new ClientMonthlyStatus();
            $cms->setClient($client);
            $cms->setPeriod($period);
            $this->em->persist($cms);
        }

        $cms->setPaymentStatus(PaymentStatus::Paid);
        $cms->setPaymentMethod($method);
        $cms->setPaymentTypeSnapshot($client->getPaymentType());
        $cms->setPaidAt(new \DateTimeImmutable());

        // Update client's last_paid_period if this payment is for a later period
        if ($client->getLastPaidPeriod() === null || strcmp($period, $client->getLastPaidPeriod()) > 0) {
            $client->setLastPaidPeriod($period);
            $client->setUpdatedAt(new \DateTimeImmutable());
        }

        // Create payment record
        $payment = new Payment();
        $payment->setClient($client);
        $payment->setAmount($amount);
        $payment->setPaymentMethod($method);
        $payment->setPeriod($period);
        $payment->setCreatedBy($actor);

        $this->em->persist($payment);
        $this->em->flush();

        $this->auditLogger->log($actor, 'client.mark_paid', 'client', $clientId, [
            'period' => $period,
            'method' => $method->value,
            'amount' => $amount,
        ]);

        return $cms;
    }
}
