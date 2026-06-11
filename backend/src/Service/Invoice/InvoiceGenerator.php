<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\ClientMonthlyStatus;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\PayMethod;
use App\Enum\PaymentStatus;
use App\Exception\InvoiceAlreadyExistsException;
use App\Exception\NoEligibleClientsException;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Config\ConfigService;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

final class InvoiceGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ClientRepository $clientRepository,
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly ConfigService $configService,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function generate(string $period, User $actor): Invoice
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            // Check if invoice already exists for this period
            $existing = $this->invoiceRepository->findOneBy(['period' => $period]);
            if ($existing !== null) {
                $conn->rollBack();
                throw new InvoiceAlreadyExistsException($existing->getInvoiceNumber());
            }

            // Get eligible fakt clients
            $clients = $this->clientRepository->findFaktClientsWithoutInvoiceForPeriod($period);

            // Get carried debts (paid via fakt in last month)
            $carriedDebts = $this->findCarriedFaktDebts();

            if (empty($clients) && empty($carriedDebts)) {
                $conn->rollBack();
                throw new NoEligibleClientsException();
            }

            $unitPrice = $this->configService->get('unit_price');
            $responsibleName = $this->configService->get('responsible_name');
            $productNameTemplate = $this->configService->get('product_name_ru_template');

            // Build product name with Russian month
            $monthNum = substr($period, 5, 2);
            $year = substr($period, 0, 4);
            $ruMonth = RuMonthMap::get($monthNum);
            $productName = str_replace(['{month}', '{year}', '{MONTH}', '{YEAR}'], [$ruMonth, $year, $ruMonth, $year], $productNameTemplate);

            // Generate invoice number
            $numData = $this->numberGenerator->nextFor($period);

            $invoice = new Invoice();
            $invoice->setInvoiceNumber($numData['number']);
            $invoice->setPeriod($period);
            $invoice->setSerialNo($numData['serial']);
            $invoice->setIssueDate(new \DateTimeImmutable());
            $invoice->setResponsibleName($responsibleName);
            $invoice->setUnitPriceSnapshot($unitPrice);
            $invoice->setProductNameSnapshot($productName);
            $invoice->setCreatedBy($actor);

            $this->em->persist($invoice);

            $totalAmount = '0.00';
            $itemsCount = 0;

            // Add items for eligible clients
            foreach ($clients as $client) {
                $quantity = $client->getProductCount();
                $totalPrice = bcmul($unitPrice, (string) $quantity, 2);

                $item = new InvoiceItem();
                $item->setClient($client);
                $item->setClientNameSnapshot($client->getName());
                $item->setClientInnSnapshot($client->getInn());
                $item->setClientPhoneSnapshot($client->getPhone());
                $item->setPaymentTypeSnapshot($client->getPaymentType());
                $item->setQuantity($quantity);
                $item->setUnitPrice($unitPrice);
                $item->setTotalPrice($totalPrice);
                $item->setIsCarriedDebt(false);

                $invoice->addItem($item);

                $totalAmount = bcadd($totalAmount, $totalPrice, 2);
                $itemsCount++;

                // UPSERT CMS as paid via fakt
                $this->upsertCms($client, $period, $invoice);
            }

            // Add carried debt items
            foreach ($carriedDebts as $debt) {
                $client = $debt->getClient();
                $quantity = $client->getProductCount();
                $debtTotalPrice = $debt->getAmount();

                $item = new InvoiceItem();
                $item->setClient($client);
                $item->setClientNameSnapshot($client->getName());
                $item->setClientInnSnapshot($client->getInn());
                $item->setClientPhoneSnapshot($client->getPhone());
                $item->setPaymentTypeSnapshot($debt->getPaymentTypeSnapshot());
                $item->setQuantity($debt->getMonthsOverdue() * $quantity);
                $item->setUnitPrice($unitPrice);
                $item->setTotalPrice($debtTotalPrice);
                $item->setIsCarriedDebt(true);
                $item->setDebt($debt);

                $invoice->addItem($item);

                $totalAmount = bcadd($totalAmount, $debtTotalPrice, 2);
                $itemsCount++;
            }

            $invoice->setTotalAmount($totalAmount);
            $invoice->setItemsCount($itemsCount);

            $this->em->flush();
            $conn->commit();

            $this->auditLogger->log($actor, 'invoice.generated', 'invoice', $invoice->getId(), [
                'period' => $period,
                'items_count' => $itemsCount,
                'total_amount' => $totalAmount,
            ]);

            // Barcha xodimlarga bildirishnoma yuborish
            $this->notificationService->notifyAllStaff(
                NotificationType::InvoiceGenerated,
                'Yangi faktura yaratildi',
                sprintf(
                    'Faktura #%s (%s davr): %d ta mijoz, jami %s so\'m',
                    $invoice->getInvoiceNumber(),
                    $period,
                    $itemsCount,
                    number_format((float) $totalAmount, 0, '.', ' '),
                ),
                '/invoices',
            );
            $this->notificationService->flush();

            return $invoice;
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    private function upsertCms($client, string $period, Invoice $invoice): void
    {
        $existing = $this->em->getRepository(ClientMonthlyStatus::class)->findOneBy([
            'client' => $client,
            'period' => $period,
        ]);

        if ($existing !== null) {
            $existing->setPaymentStatus(PaymentStatus::Paid);
            $existing->setPaymentMethod(PayMethod::Fakt);
            $existing->setInvoice($invoice);
            $existing->setPaidAt(new \DateTimeImmutable());
        } else {
            $cms = new ClientMonthlyStatus();
            $cms->setClient($client);
            $cms->setPeriod($period);
            $cms->setPaymentStatus(PaymentStatus::Paid);
            $cms->setPaymentMethod(PayMethod::Fakt);
            $cms->setPaymentTypeSnapshot($client->getPaymentType());
            $cms->setInvoice($invoice);
            $cms->setPaidAt(new \DateTimeImmutable());
            $this->em->persist($cms);
        }
    }

    /**
     * @return \App\Entity\Debt[]
     */
    private function findCarriedFaktDebts(): array
    {
        $since = (new \DateTimeImmutable())->modify('-1 month');

        return $this->em->createQueryBuilder()
            ->select('d')
            ->from(\App\Entity\Debt::class, 'd')
            ->where('d.status = :status')
            ->andWhere('d.paidMethod = :method')
            ->andWhere('d.paidAt >= :since')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\InvoiceItem ii WHERE ii.debt = d
            )')
            ->setParameter('status', 'paid')
            ->setParameter('method', 'fakt')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }
}
