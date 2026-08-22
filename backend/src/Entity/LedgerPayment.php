<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ledger_payments')]
#[ORM\Index(name: 'idx_ledger_payment_entry', columns: ['ledger_entry_id'])]
class LedgerPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LedgerEntry::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'ledger_entry_id', nullable: false, onDelete: 'CASCADE')]
    private LedgerEntry $ledgerEntry;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $amount;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(LedgerEntry $ledgerEntry, string $amount)
    {
        $this->ledgerEntry = $ledgerEntry;
        $this->amount = $amount;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLedgerEntry(): LedgerEntry
    {
        return $this->ledgerEntry;
    }

    public function getAmount(): string
    {
        return $this->amount;
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
