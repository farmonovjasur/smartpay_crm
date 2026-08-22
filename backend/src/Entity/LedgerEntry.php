<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LedgerStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ledger_entries')]
#[ORM\Index(name: 'idx_ledger_client', columns: ['client_id'])]
#[ORM\Index(name: 'idx_ledger_status', columns: ['status'])]
#[ORM\Index(name: 'idx_ledger_created', columns: ['created_at'])]
class LedgerEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: false, onDelete: 'RESTRICT')]
    private Client $client;

    /** @var Collection<int, LedgerItem> */
    #[ORM\OneToMany(targetEntity: LedgerItem::class, mappedBy: 'ledgerEntry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    /** @var Collection<int, LedgerPayment> */
    #[ORM\OneToMany(targetEntity: LedgerPayment::class, mappedBy: 'ledgerEntry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $payments;

    #[ORM\Column(name: 'total_amount', type: Types::DECIMAL, precision: 15, scale: 2, options: ['default' => '0.00'])]
    private string $totalAmount = '0.00';

    #[ORM\Column(name: 'paid_amount', type: Types::DECIMAL, precision: 15, scale: 2, options: ['default' => '0.00'])]
    private string $paidAmount = '0.00';

    #[ORM\Column(type: Types::STRING, length: 10, enumType: LedgerStatus::class, options: ['default' => 'active'])]
    private LedgerStatus $status = LedgerStatus::Active;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'paid_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $paidBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->payments = new ArrayCollection();
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

    /** @return Collection<int, LedgerItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(LedgerItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setLedgerEntry($this);
        }
        return $this;
    }

    public function removeItem(LedgerItem $item): self
    {
        $this->items->removeElement($item);
        return $this;
    }

    public function clearItems(): self
    {
        $this->items->clear();
        return $this;
    }

    /**
     * @return Collection<int, LedgerPayment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): self
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getPaidAmount(): string
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(string $paidAmount): self
    {
        $this->paidAmount = $paidAmount;
        return $this;
    }

    /**
     * Qisman to'langan summani oshirish.
     */
    public function addPaidAmount(string $amount): self
    {
        $this->paidAmount = bcadd($this->paidAmount, $amount, 2);
        return $this;
    }

    /**
     * Qolgan qarz summasi: totalAmount - paidAmount.
     */
    public function getRemainingAmount(): string
    {
        return bcsub($this->totalAmount, $this->paidAmount, 2);
    }

    /**
     * Items summalarini qayta hisoblash va totalAmount'ga yozish.
     */
    public function recalculateTotal(): self
    {
        $sum = '0.00';
        foreach ($this->items as $item) {
            $sum = bcadd($sum, $item->getAmount(), 2);
        }
        $this->totalAmount = $sum;
        return $this;
    }

    public function getStatus(): LedgerStatus
    {
        return $this->status;
    }

    public function setStatus(LedgerStatus $status): self
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

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;
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
