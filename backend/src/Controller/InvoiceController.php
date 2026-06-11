<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Security\Voter\InvoiceVoter;
use App\Service\Audit\AuditLogger;
use App\Service\Invoice\InvoiceGenerator;
use App\Service\Invoice\InvoiceXlsxRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/invoices')]
final class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceGenerator $invoiceGenerator,
        private readonly InvoiceXlsxRenderer $xlsxRenderer,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[Route('', name: 'invoice_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', '20')));

        $qb = $this->invoiceRepository->createQueryBuilder('i')
            ->orderBy('i.id', 'DESC');

        $total = (int) (clone $qb)->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();

        $items = $qb->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (Invoice $inv) => [
            'id' => $inv->getId(),
            'invoice_number' => $inv->getInvoiceNumber(),
            'period' => $inv->getPeriod(),
            'issue_date' => $inv->getIssueDate()->format('Y-m-d'),
            'total_amount' => $inv->getTotalAmount(),
            'items_count' => $inv->getItemsCount(),
            'responsible_name' => $inv->getResponsibleName(),
            'created_at' => $inv->getCreatedAt()->format('c'),
        ], $items);

        return new JsonResponse(['data' => $data, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
    }

    #[Route('/{id}', name: 'invoice_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::VIEW);

        $invoice = $this->findOrFail($id);
        $items = [];
        foreach ($invoice->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'client_name' => $item->getClientNameSnapshot(),
                'client_inn' => $item->getClientInnSnapshot(),
                'client_phone' => $item->getClientPhoneSnapshot(),
                'payment_type' => $item->getPaymentTypeSnapshot()->value,
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
                'total_price' => $item->getTotalPrice(),
                'is_carried_debt' => $item->isCarriedDebt(),
            ];
        }

        return new JsonResponse(['data' => [
            'id' => $invoice->getId(),
            'invoice_number' => $invoice->getInvoiceNumber(),
            'period' => $invoice->getPeriod(),
            'issue_date' => $invoice->getIssueDate()->format('Y-m-d'),
            'total_amount' => $invoice->getTotalAmount(),
            'items_count' => $invoice->getItemsCount(),
            'responsible_name' => $invoice->getResponsibleName(),
            'unit_price_snapshot' => $invoice->getUnitPriceSnapshot(),
            'product_name_snapshot' => $invoice->getProductNameSnapshot(),
            'items' => $items,
            'created_at' => $invoice->getCreatedAt()->format('c'),
        ]]);
    }

    #[Route('/generate', name: 'invoice_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::CREATE);

        $data = json_decode($request->getContent(), true) ?? [];
        $period = $data['period'] ?? '';

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return new JsonResponse(['error' => 'Invalid period format.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $invoice = $this->invoiceGenerator->generate($period, $actor);

        return new JsonResponse([
            'data' => [
                'id' => $invoice->getId(),
                'invoice_number' => $invoice->getInvoiceNumber(),
                'period' => $invoice->getPeriod(),
                'total_amount' => $invoice->getTotalAmount(),
                'items_count' => $invoice->getItemsCount(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/download', name: 'invoice_download', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function download(int $id): Response
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::VIEW);

        $invoice = $this->findOrFail($id);

        return $this->xlsxRenderer->render($invoice);
    }

    #[Route('/{id}', name: 'invoice_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(InvoiceVoter::DELETE);

        $invoice = $this->findOrFail($id);
        $invoiceNumber = $invoice->getInvoiceNumber();

        // client_monthly_status — bu faktura bilan bog'langan yozuvlarni tozalash
        $this->em->getConnection()->executeStatement(
            'DELETE FROM client_monthly_status WHERE invoice_id = ?',
            [$id]
        );

        // invoice_items — CASCADE orqali o'chadi, lekin aniq bo'lishi uchun
        $this->em->getConnection()->executeStatement(
            'DELETE FROM invoice_items WHERE invoice_id = ?',
            [$id]
        );

        // invoice — to'liq o'chirish (hard delete)
        $this->em->getConnection()->executeStatement(
            'DELETE FROM invoices WHERE id = ?',
            [$id]
        );

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $this->auditLogger->log($actor, 'invoice.deleted', 'invoice', $id, [
            'invoice_number' => $invoiceNumber,
        ]);

        return new JsonResponse(['message' => 'Invoice deleted.']);
    }

    private function findOrFail(int $id): Invoice
    {
        $invoice = $this->invoiceRepository->find($id);
        if ($invoice === null) {
            throw new NotFoundHttpException('Invoice not found.');
        }
        return $invoice;
    }
}
