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
            $this->em->flush(); // Generate ID for DBAL inserts

            $totalAmount = '0.00';
            $itemsCount = 0;
            $nowStr = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $invoiceId = $invoice->getId();

            $invoiceItemsParams = [];
            $cmsParams = [];
            $clientIdsToUpdate = [];

            // Add items for eligible clients
            foreach ($clients as $client) {
                $quantity = $client->getProductCount();
                $totalPrice = bcmul($unitPrice, (string) $quantity, 2);

                $invoiceItemsParams[] = [
                    $invoiceId,
                    $client->getId(),
                    $client->getName(),
                    $client->getInn(),
                    $client->getPhone() ?? '',
                    $client->getPaymentType()->value,
                    $quantity,
                    $unitPrice,
                    $totalPrice,
                    0, // is_carried_debt
                    null, // debt_id
                    $nowStr
                ];

                $cmsParams[] = [
                    $client->getId(),
                    $period,
                    PaymentStatus::Paid->value,
                    PayMethod::Fakt->value,
                    $client->getPaymentType()->value,
                    $invoiceId,
                    $nowStr,
                    $nowStr
                ];

                $totalAmount = bcadd($totalAmount, $totalPrice, 2);
                $itemsCount++;

                $currentLastPaid = $client->getLastPaidPeriod();
                if ($currentLastPaid === null || strcmp($period, $currentLastPaid) > 0) {
                    $clientIdsToUpdate[] = $client->getId();
                }
            }

            // Add carried debt items
            foreach ($carriedDebts as $debt) {
                $client = $debt->getClient();
                $quantity = $client->getProductCount();
                $debtTotalPrice = $debt->getAmount();

                $invoiceItemsParams[] = [
                    $invoiceId,
                    $client->getId(),
                    $client->getName(),
                    $client->getInn(),
                    $client->getPhone() ?? '',
                    $debt->getPaymentTypeSnapshot()->value,
                    $debt->getMonthsOverdue() * $quantity,
                    $unitPrice,
                    $debtTotalPrice,
                    1, // is_carried_debt
                    $debt->getId(),
                    $nowStr
                ];

                $totalAmount = bcadd($totalAmount, $debtTotalPrice, 2);
                $itemsCount++;
            }

            $invoice->setTotalAmount($totalAmount);
            $invoice->setItemsCount($itemsCount);

            // Execute DBAL Bulk Inserts (1000s of rows in < 1 second)
            if (!empty($invoiceItemsParams)) {
                $this->bulkInsertInvoiceItems($conn, $invoiceItemsParams);
            }

            if (!empty($cmsParams)) {
                $this->bulkUpsertCms($conn, $cmsParams);
            }

            if (!empty($clientIdsToUpdate)) {
                $this->bulkUpdateClients($conn, $clientIdsToUpdate, $period, $nowStr);
            }

            $this->em->flush(); // Flush the updated total amount and count on invoice
            $conn->commit();

            $this->auditLogger->log($actor, 'invoice.generated', 'invoice', $invoiceId, [
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

    private function bulkInsertInvoiceItems($conn, array $data): void
    {
        $chunkSize = 500;
        foreach (array_chunk($data, $chunkSize) as $chunk) {
            $placeholders = [];
            $params = [];
            foreach ($chunk as $row) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                foreach ($row as $val) {
                    $params[] = $val;
                }
            }
            $sql = 'INSERT INTO invoice_items (invoice_id, client_id, client_name_snapshot, client_inn_snapshot, client_phone_snapshot, payment_type_snapshot, quantity, unit_price, total_price, is_carried_debt, debt_id, created_at) VALUES ' . implode(', ', $placeholders);
            $conn->executeStatement($sql, $params);
        }
    }

    private function bulkUpsertCms($conn, array $data): void
    {
        $chunkSize = 500;
        foreach (array_chunk($data, $chunkSize) as $chunk) {
            $placeholders = [];
            $params = [];
            foreach ($chunk as $row) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
                foreach ($row as $val) {
                    $params[] = $val;
                }
            }
            $sql = 'INSERT INTO client_monthly_status (client_id, period, payment_status, payment_method, payment_type_snapshot, invoice_id, paid_at, created_at) VALUES ' . implode(', ', $placeholders) . ' ON DUPLICATE KEY UPDATE invoice_id = VALUES(invoice_id), payment_status = VALUES(payment_status), payment_method = VALUES(payment_method), paid_at = VALUES(paid_at)';
            $conn->executeStatement($sql, $params);
        }
    }

    private function bulkUpdateClients($conn, array $clientIds, string $period, string $nowStr): void
    {
        $chunkSize = 1000;
        foreach (array_chunk($clientIds, $chunkSize) as $chunk) {
            $placeholders = str_repeat('?,', count($chunk) - 1) . '?';
            $params = array_merge([$period, $nowStr], $chunk);
            $sql = "UPDATE clients SET last_paid_period = ?, updated_at = ? WHERE id IN ($placeholders)";
            $conn->executeStatement($sql, $params);
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
