<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_items')]
#[ORM\Index(name: 'idx_inv_items_invoice', columns: ['invoice_id'])]
#[ORM\Index(name: 'idx_inv_items_client', columns: ['client_id'])]
class InvoiceItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
    private Invoice $invoice;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: false, onDelete: 'RESTRICT')]
    private Client $client;

    #[ORM\Column(name: 'client_name_snapshot', type: Types::STRING, length: 255)]
    private string $clientNameSnapshot;

    #[ORM\Column(name: 'client_inn_snapshot', type: Types::STRING, length: 14)]
    private string $clientInnSnapshot;

    #[ORM\Column(name: 'client_phone_snapshot', type: Types::STRING, length: 20)]
    private string $clientPhoneSnapshot;

    #[ORM\Column(name: 'payment_type_snapshot', type: Types::STRING, length: 10, enumType: PaymentType::class)]
    private PaymentType $paymentTypeSnapshot;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private int $quantity;

    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(name: 'total_price', type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $totalPrice;

    #[ORM\Column(name: 'is_carried_debt', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isCarriedDebt = false;

    #[ORM\ManyToOne(targetEntity: Debt::class)]
    #[ORM\JoinColumn(name: 'debt_id', nullable: true, onDelete: 'SET NULL')]
    private ?Debt $debt = null;

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

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): self
    {
        $this->invoice = $invoice;
        return $this;
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

    public function getClientNameSnapshot(): string
    {
        return $this->clientNameSnapshot;
    }

    public function setClientNameSnapshot(string $clientNameSnapshot): self
    {
        $this->clientNameSnapshot = $clientNameSnapshot;
        return $this;
    }

    public function getClientInnSnapshot(): string
    {
        return $this->clientInnSnapshot;
    }

    public function setClientInnSnapshot(string $clientInnSnapshot): self
    {
        $this->clientInnSnapshot = $clientInnSnapshot;
        return $this;
    }

    public function getClientPhoneSnapshot(): string
    {
        return $this->clientPhoneSnapshot;
    }

    public function setClientPhoneSnapshot(string $clientPhoneSnapshot): self
    {
        $this->clientPhoneSnapshot = $clientPhoneSnapshot;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function isCarriedDebt(): bool
    {
        return $this->isCarriedDebt;
    }

    public function setIsCarriedDebt(bool $isCarriedDebt): self
    {
        $this->isCarriedDebt = $isCarriedDebt;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
