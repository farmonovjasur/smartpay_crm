<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PayMethod;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'prepayments')]
#[ORM\Index(name: 'idx_prepay_client', columns: ['client_id'])]
#[ORM\Index(name: 'idx_prepay_paid_at', columns: ['paid_at'])]
class Prepayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: false, onDelete: 'RESTRICT')]
    private Client $client;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $amount;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: PayMethod::class)]
    private PayMethod $method;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $paidAt;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->paidAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getMethod(): PayMethod
    {
        return $this->method;
    }

    public function setMethod(PayMethod $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function getPaidAt(): \DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
