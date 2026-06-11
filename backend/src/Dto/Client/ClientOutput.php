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
    public bool $hasActiveDebt;
    public string $createdAt;

    public static function fromEntity(Client $client): self
    {
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
        $dto->lastPaidPeriod = $client->getLastPaidPeriod();
        $dto->hasActiveDebt = false; // overridden by ClientService when listing
        $dto->createdAt = $client->getCreatedAt()->format('c');

        return $dto;
    }
}
