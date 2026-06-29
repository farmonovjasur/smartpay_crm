<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ClientStatus;
use App\Enum\PaymentType;
use Doctrine\DBAL\Types\Types;
use App\Repository\ClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'clients')]
#[ORM\UniqueConstraint(name: 'uniq_clients_inn_alive', columns: ['inn', 'deleted_at'])]
#[ORM\Index(name: 'idx_clients_payment_type', columns: ['payment_type'])]
#[ORM\Index(name: 'idx_clients_status', columns: ['status'])]
#[ORM\Index(name: 'idx_clients_deleted', columns: ['deleted_at'])]
#[ORM\Index(name: 'idx_clients_name', columns: ['name'])]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 14)]
    private string $inn;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $phone;

    #[ORM\Column(name: 'phone2', type: Types::STRING, length: 20, nullable: true)]
    private ?string $phone2 = null;

    #[ORM\Column(name: 'service_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $serviceDate;

    #[ORM\Column(name: 'payment_type', type: Types::STRING, length: 10, enumType: PaymentType::class)]
    private PaymentType $paymentType;

    #[ORM\Column(name: 'product_count', type: Types::INTEGER, options: ['unsigned' => true])]
    private int $productCount;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: ClientStatus::class, options: ['default' => 'faol'])]
    private ClientStatus $status = ClientStatus::Faol;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'last_paid_period', type: Types::STRING, length: 7, nullable: true)]
    private ?string $lastPaidPeriod = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, options: ['default' => '0.00'])]
    private string $balance = '0.00';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInn(): string
    {
        return $this->inn;
    }

    public function setInn(string $inn): self
    {
        $this->inn = $inn;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getPhone2(): ?string
    {
        return $this->phone2;
    }

    public function setPhone2(?string $phone2): self
    {
        $this->phone2 = $phone2;
        return $this;
    }

    public function getServiceDate(): \DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function setServiceDate(\DateTimeImmutable $serviceDate): self
    {
        $this->serviceDate = $serviceDate;
        return $this;
    }

    public function getPaymentType(): PaymentType
    {
        return $this->paymentType;
    }

    public function setPaymentType(PaymentType $paymentType): self
    {
        $this->paymentType = $paymentType;
        return $this;
    }

    public function getProductCount(): int
    {
        return $this->productCount;
    }

    public function setProductCount(int $productCount): self
    {
        $this->productCount = $productCount;
        return $this;
    }

    public function getStatus(): ClientStatus
    {
        return $this->status;
    }

    public function setStatus(ClientStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getLastPaidPeriod(): ?string
    {
        return $this->lastPaidPeriod;
    }

    public function setLastPaidPeriod(?string $lastPaidPeriod): self
    {
        $this->lastPaidPeriod = $lastPaidPeriod;
        return $this;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): self
    {
        $this->balance = $balance;
        return $this;
    }

    public function addBalance(string $amount): self
    {
        $this->balance = bcadd($this->balance, $amount, 2);
        return $this;
    }

    public function deductBalance(string $amount): self
    {
        $this->balance = bcsub($this->balance, $amount, 2);
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): self
    {
        $this->deletedAt = new \DateTimeImmutable();
        return $this;
    }
}
