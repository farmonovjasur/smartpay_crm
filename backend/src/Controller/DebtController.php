<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Debt;
use App\Enum\DebtStatus;
use App\Enum\PayMethod;
use App\Service\Debt\PaymentProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/debtors')]
final class DebtController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaymentProcessor $paymentProcessor,
    ) {
    }

    #[Route('/export', name: 'debtor_export', methods: ['GET'])]
    public function export(Request $request, \App\Service\Debt\DebtExporter $exporter): Response
    {
        $status = $request->query->get('status', 'active');
        return $exporter->exportFiltered($status);
    }

    #[Route('', name: 'debtor_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', '20')));
        $status = $request->query->get('status', 'active');

        $qb = $this->em->createQueryBuilder()
            ->select('d')
            ->from(Debt::class, 'd')
            ->innerJoin('d.client', 'c')
            ->orderBy('d.id', 'DESC');

        if ($status !== 'all') {
            $qb->andWhere('d.status = :status')
                ->setParameter('status', $status);
        }

        $total = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();

        $debts = $qb->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (Debt $d) => [
            'id' => $d->getId(),
            'client_id' => $d->getClient()->getId(),
            'client_name' => $d->getClient()->getName(),
            'client_inn' => $d->getClient()->getInn(),
            'amount' => $d->getAmount(),
            'monthly_amount' => $d->getMonthlyAmount(),
            'months_overdue' => $d->getMonthsOverdue(),
            'first_overdue_period' => $d->getFirstOverduePeriod(),
            'last_overdue_period' => $d->getLastOverduePeriod(),
            'payment_type_snapshot' => $d->getPaymentTypeSnapshot()->value,
            'status' => $d->getStatus()->value,
            'due_date' => $d->getDueDate()->format('Y-m-d'),
        ], $debts);

        return new JsonResponse(['data' => $data, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
    }

    #[Route('/{id}', name: 'debtor_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $debt = $this->em->find(Debt::class, $id);
        if ($debt === null) {
            throw new NotFoundHttpException('Debt not found.');
        }

        return new JsonResponse(['data' => [
            'id' => $debt->getId(),
            'client_id' => $debt->getClient()->getId(),
            'client_name' => $debt->getClient()->getName(),
            'amount' => $debt->getAmount(),
            'monthly_amount' => $debt->getMonthlyAmount(),
            'months_overdue' => $debt->getMonthsOverdue(),
            'first_overdue_period' => $debt->getFirstOverduePeriod(),
            'last_overdue_period' => $debt->getLastOverduePeriod(),
            'payment_type_snapshot' => $debt->getPaymentTypeSnapshot()->value,
            'status' => $debt->getStatus()->value,
            'paid_at' => $debt->getPaidAt()?->format('c'),
            'paid_method' => $debt->getPaidMethod()?->value,
        ]]);
    }

    #[Route('/{id}/pay', name: 'debtor_pay', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function pay(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $method = $data['method'] ?? '';

        if (!in_array($method, ['fakt', 'naqt'], true)) {
            return new JsonResponse(['error' => 'method must be fakt or naqt'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $debt = $this->paymentProcessor->payDebt($id, PayMethod::from($method), $actor);

        return new JsonResponse([
            'message' => 'Debt paid successfully.',
            'data' => [
                'id' => $debt->getId(),
                'status' => $debt->getStatus()->value,
                'paid_method' => $debt->getPaidMethod()?->value,
                'amount' => $debt->getAmount(),
            ],
        ]);
    }
}
