<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\PayMethod;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'client_monthly_status')]
#[ORM\UniqueConstraint(name: 'uniq_client_period', columns: ['client_id', 'period'])]
#[ORM\Index(name: 'idx_period', columns: ['period'])]
#[ORM\Index(name: 'idx_status', columns: ['payment_status'])]
class ClientMonthlyStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: false, onDelete: 'RESTRICT')]
    private Client $client;

    #[ORM\Column(type: Types::STRING, length: 7)]
    private string $period;

    #[ORM\Column(name: 'payment_status', type: Types::STRING, length: 10, enumType: PaymentStatus::class)]
    private PaymentStatus $paymentStatus;

    #[ORM\Column(name: 'payment_method', type: Types::STRING, length: 10, nullable: true, enumType: PayMethod::class)]
    private ?PayMethod $paymentMethod = null;

    #[ORM\Column(name: 'payment_type_snapshot', type: Types::STRING, length: 10, enumType: PaymentType::class)]
    private PaymentType $paymentTypeSnapshot;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: true, onDelete: 'SET NULL')]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne(targetEntity: Debt::class)]
    #[ORM\JoinColumn(name: 'debt_id', nullable: true, onDelete: 'SET NULL')]
    private ?Debt $debt = null;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

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

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function setPeriod(string $period): self
    {
        $this->period = $period;
        return $this;
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(PaymentStatus $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    public function getPaymentMethod(): ?PayMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PayMethod $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
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

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getDebt(): ?Debt
    {
        return $this->debt;
    }

    public function setDebt(?Debt $debt): self
    {
        $this->debt = $debt;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
