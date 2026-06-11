<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_number', columns: ['invoice_number'])]
#[ORM\UniqueConstraint(name: 'uniq_invoice_period', columns: ['period', 'deleted_at'])]
#[ORM\Index(name: 'idx_invoices_issue_date', columns: ['issue_date'])]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'invoice_number', type: Types::STRING, length: 50)]
    private string $invoiceNumber;

    #[ORM\Column(type: Types::STRING, length: 7)]
    private string $period;

    #[ORM\Column(name: 'serial_no', type: Types::INTEGER, options: ['unsigned' => true])]
    private int $serialNo;

    #[ORM\Column(name: 'issue_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(name: 'total_amount', type: Types::DECIMAL, precision: 15, scale: 2, options: ['default' => '0'])]
    private string $totalAmount = '0.00';

    #[ORM\Column(name: 'items_count', type: Types::INTEGER, options: ['unsigned' => true, 'default' => 0])]
    private int $itemsCount = 0;

    #[ORM\Column(name: 'responsible_name', type: Types::STRING, length: 255, options: ['default' => 'Halimov Bekzod'])]
    private string $responsibleName = 'Halimov Bekzod';

    #[ORM\Column(name: 'unit_price_snapshot', type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $unitPriceSnapshot;

    #[ORM\Column(name: 'product_name_snapshot', type: Types::STRING, length: 500)]
    private string $productNameSnapshot;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var Collection<int, InvoiceItem> */
    #[ORM\OneToMany(targetEntity: InvoiceItem::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;
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

    public function getSerialNo(): int
    {
        return $this->serialNo;
    }

    public function setSerialNo(int $serialNo): self
    {
        $this->serialNo = $serialNo;
        return $this;
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): self
    {
        $this->issueDate = $issueDate;
        return $this;
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

    public function getItemsCount(): int
    {
        return $this->itemsCount;
    }

    public function setItemsCount(int $itemsCount): self
    {
        $this->itemsCount = $itemsCount;
        return $this;
    }

    public function getResponsibleName(): string
    {
        return $this->responsibleName;
    }

    public function setResponsibleName(string $responsibleName): self
    {
        $this->responsibleName = $responsibleName;
        return $this;
    }

    public function getUnitPriceSnapshot(): string
    {
        return $this->unitPriceSnapshot;
    }

    public function setUnitPriceSnapshot(string $unitPriceSnapshot): self
    {
        $this->unitPriceSnapshot = $unitPriceSnapshot;
        return $this;
    }

    public function getProductNameSnapshot(): string
    {
        return $this->productNameSnapshot;
    }

    public function setProductNameSnapshot(string $productNameSnapshot): self
    {
        $this->productNameSnapshot = $productNameSnapshot;
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

    /** @return Collection<int, InvoiceItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(InvoiceItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInvoice($this);
        }
        return $this;
    }

    public function removeItem(InvoiceItem $item): self
    {
        $this->items->removeElement($item);
        return $this;
    }
}
