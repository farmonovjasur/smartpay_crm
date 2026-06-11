<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\User\ResetPasswordRequest;
use App\Dto\User\UserCreateInput;
use App\Dto\User\UserOutput;
use App\Dto\User\UserUpdateInput;
use App\Security\Voter\UserVoter;
use App\Service\User\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'user_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', '20')));
        $search = $request->query->get('search');

        $result = $this->userService->findPaginated($page, $pageSize, $search);

        $items = array_map(
            fn ($user) => UserOutput::fromEntity($user),
            $result['items'],
        );

        return new JsonResponse([
            'data' => $items,
            'total' => $result['total'],
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('/{id}', name: 'user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW);

        $user = $this->userService->findById($id);

        return new JsonResponse(['data' => UserOutput::fromEntity($user)]);
    }

    #[Route('', name: 'user_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::CREATE);

        $data = json_decode($request->getContent(), true) ?? [];

        $input = new UserCreateInput();
        $input->name = $data['name'] ?? '';
        $input->email = $data['email'] ?? '';
        $input->password = $data['password'] ?? '';
        $input->role = $data['role'] ?? 'user';

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

        $user = $this->userService->create($input, $actor);

        return new JsonResponse(['data' => UserOutput::fromEntity($user)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'user_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::UPDATE);

        $data = json_decode($request->getContent(), true) ?? [];

        $input = new UserUpdateInput();
        $input->name = $data['name'] ?? '';
        $input->email = $data['email'] ?? '';
        $input->role = $data['role'] ?? 'user';
        $input->isActive = $data['is_active'] ?? true;

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

        $user = $this->userService->update($id, $input, $actor);

        return new JsonResponse(['data' => UserOutput::fromEntity($user)]);
    }

    #[Route('/{id}', name: 'user_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::DELETE);

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();

        $this->userService->softDelete($id, $actor);

        return new JsonResponse(['message' => 'User deleted.'], Response::HTTP_OK);
    }

    #[Route('/{id}/reset-password', name: 'user_reset_password', methods: ['POST'])]
    public function resetPassword(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::RESET_PASSWORD);

        $data = json_decode($request->getContent(), true) ?? [];

        $input = new ResetPasswordRequest();
        $input->newPassword = $data['new_password'] ?? null;

        // Only validate if password is provided
        if ($input->newPassword !== null && $input->newPassword !== '') {
            $errors = $this->validator->validate($input);
            if (count($errors) > 0) {
                $messages = [];
                foreach ($errors as $error) {
                    $messages[$error->getPropertyPath()][] = $error->getMessage();
                }
                return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        /** @var \App\Entity\User $actor */
        $actor = $this->getUser();

        $newPassword = $this->userService->resetPassword($id, $input->newPassword, $actor);

        return new JsonResponse([
            'message' => 'Password reset successfully.',
            'new_password' => $newPassword,
        ]);
    }
}
