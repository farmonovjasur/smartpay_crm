<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DebtStatus;
use App\Enum\PayMethod;
use App\Enum\PaymentType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'debts')]
#[ORM\UniqueConstraint(name: 'uniq_active_debt_per_client', columns: ['client_id', 'is_active'])]
#[ORM\Index(name: 'idx_debts_status', columns: ['status'])]
#[ORM\Index(name: 'idx_debts_due_date', columns: ['due_date'])]
class Debt
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

    #[ORM\Column(name: 'monthly_amount', type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $monthlyAmount;

    #[ORM\Column(name: 'months_overdue', type: Types::INTEGER, options: ['unsigned' => true, 'default' => 1])]
    private int $monthsOverdue = 1;

    #[ORM\Column(name: 'first_overdue_period', type: Types::STRING, length: 7)]
    private string $firstOverduePeriod;

    #[ORM\Column(name: 'last_overdue_period', type: Types::STRING, length: 7)]
    private string $lastOverduePeriod;

    #[ORM\Column(name: 'payment_type_snapshot', type: Types::STRING, length: 10, enumType: PaymentType::class)]
    private PaymentType $paymentTypeSnapshot;

    #[ORM\Column(name: 'due_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(name: 'paid_method', type: Types::STRING, length: 10, nullable: true, enumType: PayMethod::class)]
    private ?PayMethod $paidMethod = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'paid_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $paidBy = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: DebtStatus::class, options: ['default' => 'active'])]
    private DebtStatus $status = DebtStatus::Active;

    #[ORM\Column(name: 'is_active', type: Types::SMALLINT, nullable: true, insertable: false, updatable: false, columnDefinition: "TINYINT GENERATED ALWAYS AS (CASE WHEN status='active' THEN 1 ELSE NULL END) STORED")]
    private ?int $isActive = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
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

    public function getMonthlyAmount(): string
    {
        return $this->monthlyAmount;
    }

    public function setMonthlyAmount(string $monthlyAmount): self
    {
        $this->monthlyAmount = $monthlyAmount;
        return $this;
    }

    public function getMonthsOverdue(): int
    {
        return $this->monthsOverdue;
    }

    public function setMonthsOverdue(int $monthsOverdue): self
    {
        $this->monthsOverdue = $monthsOverdue;
        return $this;
    }

    public function getFirstOverduePeriod(): string
    {
        return $this->firstOverduePeriod;
    }

    public function setFirstOverduePeriod(string $firstOverduePeriod): self
    {
        $this->firstOverduePeriod = $firstOverduePeriod;
        return $this;
    }

    public function getLastOverduePeriod(): string
    {
        return $this->lastOverduePeriod;
    }

    public function setLastOverduePeriod(string $lastOverduePeriod): self
    {
        $this->lastOverduePeriod = $lastOverduePeriod;
        return $this;
    }

    public function getPaymentTypeSnapshot(): PaymentType
    {
        return $this->paymentTypeSnapshot;
    }

    public function setPaymentTypeSnapshot(PaymentType $paymentTypeSnapshot): self
    {
        $this->paymentTypeSnapshot = $paymentTypeSnapshot;
        return $this;
    }

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeImmutable $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getPaidMethod(): ?PayMethod
    {
        return $this->paidMethod;
    }

    public function setPaidMethod(?PayMethod $paidMethod): self
    {
        $this->paidMethod = $paidMethod;
        return $this;
    }

    public function getPaidBy(): ?User
    {
        return $this->paidBy;
    }

    public function setPaidBy(?User $paidBy): self
    {
        $this->paidBy = $paidBy;
        return $this;
    }

    public function getStatus(): DebtStatus
    {
        return $this->status;
    }

    public function setStatus(DebtStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getIsActive(): ?int
    {
        return $this->isActive;
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
}
