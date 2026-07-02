<?php

declare(strict_types=1);

namespace App\Dto\Client;

use App\Entity\Client;

final class ClientOutput
{
    public int $id;
    public string $inn;
    public string $name;
    public string $phone;
    public ?string $phone2;
    public string $serviceDate;
    public string $paymentType;
    public int $productCount;
    public string $status;
    public ?string $notes;
    public ?string $lastPaidPeriod;
    public string $balance;
    public bool $hasActiveDebt;
    /** Real-time computed: last_paid_period < current month */
    public bool $isOverdue;
    public string $createdAt;

    public static function fromEntity(Client $client): self
    {
        $currentPeriod = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))->format('Y-m');
        $lastPaid = $client->getLastPaidPeriod();

        $dto = new self();
        $dto->id = $client->getId();
        $dto->inn = $client->getInn();
        $dto->name = $client->getName();
        $dto->phone = $client->getPhone();
        $dto->phone2 = $client->getPhone2();
        $dto->serviceDate = $client->getServiceDate()->format('Y-m-d');
        $dto->paymentType = $client->getPaymentType()->value;
        $dto->productCount = $client->getProductCount();
        $dto->status = $client->getStatus()->value;
        $dto->notes = $client->getNotes();
        $dto->lastPaidPeriod = $lastPaid;
        $dto->balance = $client->getBalance();
        $dto->hasActiveDebt = false; // overridden by ClientService when listing
        $dto->isOverdue = $lastPaid !== null && strcmp($lastPaid, $currentPeriod) < 0;
        $dto->createdAt = $client->getCreatedAt()->format('c');

        return $dto;
    }
}
