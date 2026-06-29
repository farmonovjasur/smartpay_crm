<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Client\ClientCreateInput;
use App\Dto\Client\ClientFilter;
use App\Dto\Client\ClientOutput;
use App\Dto\Client\ClientUpdateInput;
use App\Dto\Client\MarkPaidRequest;
use App\Dto\Client\PrepayRequest;
use App\Enum\PayMethod;
use App\Security\Voter\ClientVoter;
use App\Service\Client\ClientExporter;
use App\Service\Client\ClientImporter;
use App\Service\Client\ClientService;
use App\Service\Client\MonthlyPaymentService;
use App\Service\Client\PrepaymentService;
use App\Service\Client\TemplateGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/clients')]
final class ClientController extends AbstractController
{
    public function __construct(
        private readonly ClientService $clientService,
        private readonly MonthlyPaymentService $monthlyPaymentService,
        private readonly PrepaymentService $prepaymentService,
        private readonly ClientImporter $clientImporter,
        private readonly ClientExporter $clientExporter,
        private readonly TemplateGenerator $templateGenerator,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'client_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClientVoter::VIEW);

        $filter = new ClientFilter();
        $filter->page = max(1, (int) $request->query->get('page', '1'));
        $filter->pageSize = min(100, max(1, (int) $request->query->get('pageSize', '20')));
        $filter->search = $request->query->get('search');
        $filter->paymentType = $request->query->get('payment_type');
        $filter->status = $request->query->get('status');
        $filter->sort = $request->query->get('sort', 'id_desc');

        $result = $this->clientService->findPaginated($filter);

        // Tag each client with its current debt flag (single batched query).
        $clientIds = array_map(fn ($c) => $c->getId(), $result['items']);
        $debtorIds = $this->clientService->findIdsWithActiveDebt($clientIds);

        $data = array_map(function ($c) use ($debtorIds) {
            $out = ClientOutput::fromEntity($c);
            $out->hasActiveDebt = in_array($c->getId(), $debtorIds, true);
            return $out;
        }, $result['items']);

        return new JsonResponse([
            'data' => $data,
            'total' => $result['total'],
            'page' => $filter->page,
            'pageSize' => $filter->pageSize,
        ]);
    }

    #[Route('/import', name: 'client_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClientVoter::IMPORT);

        $file = $request->files->get('file');
        if ($file === null) {
            return new JsonResponse(['error' => 'No file uploaded.'], Response::HTTP_BAD_REQUEST);
        }

        $dryRun = filter_var($request->query->get('dryRun', 'false'), FILTER_VALIDATE_BOOLEAN);

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $result = $this->clientImporter->importExcel($file, $actor, $dryRun);

        return new JsonResponse([
            'totalRows' => $result->totalRows,
            'importedCount' => $result->importedCount,
            'errorRows' => array_map(fn ($e) => ['row' => $e->row, 'errors' => $e->errors], $result->errorRows),
            'duplicateRows' => $result->duplicateRows,
            'dryRun' => $dryRun,
        ]);
    }

    #[Route('/export', name: 'client_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ClientVoter::EXPORT);

        $filter = new ClientFilter();
        $filter->search = $request->query->get('search');
        $filter->paymentType = $request->query->get('payment_type');
        $filter->status = $request->query->get('status');

        return $this->clientExporter->exportFiltered($filter);
    }

    #[Route('/template', name: 'client_template', methods: ['GET'])]
    public function template(): Response
    {
        $this->denyAccessUnlessGranted(ClientVoter::VIEW);

        return $this->templateGenerator->generate();
    }

    #[Route('/{id}', name: 'client_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClientVoter::VIEW);

        $client = $this->clientService->findById($id);
        $output = ClientOutput::fromEntity($client);
        $output->hasActiveDebt = in_array($id, $this->clientService->findIdsWithActiveDebt([$id]), true);

        return new JsonResponse(['data' => $output]);
    }

    #[Route('', name: 'client_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClientVoter::CREATE);

        $data = json_decode($request->getContent(), true) ?? [];
        $input = new ClientCreateInput();
        $input->inn = $data['inn'] ?? '';
        $input->name = $data['name'] ?? '';
        $input->phone = $data['phone'] ?? '';
        $input->phone2 = !empty($data['phone2']) ? $data['phone2'] : null;
        $input->serviceDate = $data['service_date'] ?? '';
        $input->paymentType = $data['payment_type'] ?? '';
        $input->productCount = (int) ($data['product_count'] ?? 1);
        $input->status = $data['status'] ?? 'faol';
        $input->notes = $data['notes'] ?? null;
        $input->lastPaidPeriod = (string) ($data['last_paid_period'] ?? '');

        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $client = $this->clientService->create($input, $actor);

        return new JsonResponse(['data' => ClientOutput::fromEntity($client)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'client_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClientVoter::UPDATE);

        $data = json_decode($request->getContent(), true) ?? [];
        $input = new ClientUpdateInput();
        $input->inn = $data['inn'] ?? '';
        $input->name = $data['name'] ?? '';
        $input->phone = $data['phone'] ?? '';
        $input->phone2 = !empty($data['phone2']) ? $data['phone2'] : null;
        $input->serviceDate = $data['service_date'] ?? '';
        $input->paymentType = $data['payment_type'] ?? '';
        $input->productCount = (int) ($data['product_count'] ?? 1);
        $input->status = $data['status'] ?? 'faol';
        $input->notes = $data['notes'] ?? null;
        $input->lastPaidPeriod = (string) ($data['last_paid_period'] ?? '');

        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $client = $this->clientService->update($id, $input, $actor);

        return new JsonResponse(['data' => ClientOutput::fromEntity($client)]);
    }

    #[Route('/{id}', name: 'client_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClientVoter::DELETE);

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $this->clientService->softDelete($id, $actor);

        return new JsonResponse(['message' => 'Client deleted.']);
    }

    #[Route('/{id}/mark-monthly-paid', name: 'client_mark_paid', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markMonthlyPaid(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClientVoter::MARK_PAID);

        $data = json_decode($request->getContent(), true) ?? [];
        $input = new MarkPaidRequest();
        $input->period = $data['period'] ?? '';
        $input->method = $data['method'] ?? '';

        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $cms = $this->monthlyPaymentService->markPaid($id, $input->period, PayMethod::from($input->method), $actor);

        return new JsonResponse([
            'message' => 'Payment recorded.',
            'data' => [
                'client_id' => $cms->getClient()->getId(),
                'period' => $cms->getPeriod(),
                'status' => $cms->getPaymentStatus()->value,
                'method' => $cms->getPaymentMethod()?->value,
            ],
        ]);
    }

    #[Route('/{id}/prepay', name: 'client_prepay', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function prepay(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClientVoter::PREPAY);

        $data = json_decode($request->getContent(), true) ?? [];
        $input = new PrepayRequest();
        $input->amount = (string) ($data['amount'] ?? '');
        $input->method = $data['method'] ?? '';
        $input->notes = $data['notes'] ?? null;

        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();
        $prepayment = $this->prepaymentService->deposit(
            $id,
            $input->amount,
            PayMethod::from($input->method),
            $input->notes,
            $actor
        );

        return new JsonResponse([
            'message' => "Oldindan to'lov muvaffaqiyatli qo'shildi.",
            'data' => [
                'id' => $prepayment->getId(),
                'amount' => $prepayment->getAmount(),
                'method' => $prepayment->getMethod()->value,
                'new_balance' => $prepayment->getClient()->getBalance(),
                'paid_at' => $prepayment->getPaidAt()->format('c'),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/prepayments', name: 'client_prepayments', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function prepayments(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClientVoter::VIEW);

        $history = $this->prepaymentService->getHistory($id);

        return new JsonResponse(['data' => $history]);
    }
}
