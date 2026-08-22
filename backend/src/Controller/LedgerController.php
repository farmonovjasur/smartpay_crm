<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Ledger\LedgerExporter;
use App\Service\Ledger\LedgerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Qarzdaftari (Credit Ledger) API.
 *
 * Qarzga xizmat berilgan mijozlarni boshqarish:
 * yaratish, ro'yxat, batafsil, tahrirlash, to'lash, o'chirish, eksport.
 */
#[Route('/api/ledger')]
final class LedgerController extends AbstractController
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
    }

    /**
     * Qarzdaftari ro'yxati (paginated, filtrlash, qidiruv).
     */
    #[Route('', name: 'ledger_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', '20')));
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');

        $result = $this->ledgerService->findPaginated($page, $pageSize, $status, $search);

        return new JsonResponse($result);
    }

    /**
     * Dashboard uchun umumiy statistika.
     */
    #[Route('/summary', name: 'ledger_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        return new JsonResponse($this->ledgerService->getSummary());
    }

    /**
     * Excel eksport.
     */
    #[Route('/export', name: 'ledger_export', methods: ['GET'])]
    public function export(Request $request, LedgerExporter $exporter): Response
    {
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');

        return $exporter->exportFiltered($status, $search);
    }

    /**
     * Yangi qarz yozuvi yaratish.
     */
    #[Route('', name: 'ledger_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $clientId = (int) ($data['client_id'] ?? 0);
        $items = $data['items'] ?? [];
        $notes = $data['notes'] ?? null;

        if ($clientId <= 0) {
            return new JsonResponse(['error' => 'Mijoz tanlanishi shart.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (empty($items)) {
            return new JsonResponse(['error' => 'Kamida bitta xizmat qatori kiritilishi shart.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $entry = $this->ledgerService->create($clientId, $items, $notes, $actor);

        return new JsonResponse([
            'message' => 'Qarz muvaffaqiyatli yaratildi.',
            'data' => ['id' => $entry->getId(), 'total_amount' => $entry->getTotalAmount()],
        ], Response::HTTP_CREATED);
    }

    /**
     * Bitta yozuv batafsil (items bilan).
     */
    #[Route('/{id}', name: 'ledger_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $detail = $this->ledgerService->getDetail($id);
        return new JsonResponse(['data' => $detail]);
    }

    /**
     * Qarz yozuvini tahrirlash (xato tuzatish uchun).
     */
    #[Route('/{id}', name: 'ledger_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $items = $data['items'] ?? [];
        $notes = $data['notes'] ?? null;

        if (empty($items)) {
            return new JsonResponse(['error' => 'Kamida bitta xizmat qatori kiritilishi shart.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $entry = $this->ledgerService->update($id, $items, $notes, $actor);

        return new JsonResponse([
            'message' => 'Qarz muvaffaqiyatli yangilandi.',
            'data' => ['id' => $entry->getId(), 'total_amount' => $entry->getTotalAmount()],
        ]);
    }

    /**
     * Qarzni to'lash (to'liq yoki qisman).
     */
    #[Route('/{id}/pay', name: 'ledger_pay', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function pay(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $amount = $data['amount'] ?? null;

        if ($amount === null || $amount === '') {
            return new JsonResponse(['error' => "To'lov summasi kiritilishi shart."], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $result = $this->ledgerService->pay($id, (string) $amount, $actor);

        $entry = $result['entry'];

        $message = $result['fully_paid']
            ? "Qarz to'liq to'landi."
            : sprintf("Qisman to'lov qabul qilindi. Qolgan qarz: %s so'm.", number_format((float) $result['remaining'], 0, '.', ' '));

        return new JsonResponse([
            'message' => $message,
            'data' => [
                'id' => $entry->getId(),
                'status' => $entry->getStatus()->value,
                'paid_amount' => $entry->getPaidAmount(),
                'remaining_amount' => $result['remaining'],
                'fully_paid' => $result['fully_paid'],
            ],
        ]);
    }

    /**
     * Qarz yozuvini o'chirish (faqat active holatda).
     */
    #[Route('/{id}', name: 'ledger_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $this->ledgerService->delete($id, $actor);

        return new JsonResponse(['message' => "Qarz o'chirildi."]);
    }
}
