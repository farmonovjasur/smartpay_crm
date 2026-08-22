<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ledger_items')]
#[ORM\Index(name: 'idx_litem_entry', columns: ['ledger_entry_id'])]
class LedgerItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LedgerEntry::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'ledger_entry_id', nullable: false, onDelete: 'CASCADE')]
    private LedgerEntry $ledgerEntry;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $description;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $amount;

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

    public function getLedgerEntry(): LedgerEntry
    {
        return $this->ledgerEntry;
    }

    public function setLedgerEntry(LedgerEntry $ledgerEntry): self
    {
        $this->ledgerEntry = $ledgerEntry;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
