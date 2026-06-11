<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Auth\LoginRequest;
use App\Service\Auth\AuthService;
use App\Service\Auth\CookieFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly CookieFactory $cookieFactory,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $loginRequest = new LoginRequest();
        $loginRequest->email = $data['email'] ?? '';
        $loginRequest->password = $data['password'] ?? '';

        $errors = $this->validator->validate($loginRequest);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $session = $this->authService->login(
            $loginRequest->email,
            $loginRequest->password,
            $request->getClientIp() ?? '0.0.0.0',
        );

        $response = new JsonResponse([
            'user' => [
                'id' => $session->user->getId(),
                'name' => $session->user->getName(),
                'email' => $session->user->getEmail(),
                'role' => $session->user->getRole()->value,
            ],
        ], Response::HTTP_OK);

        $response->headers->setCookie($this->cookieFactory->access($session->accessToken, $session->accessTtl));
        $response->headers->setCookie($this->cookieFactory->refresh($session->refreshToken, $session->refreshTtl));
        $response->headers->setCookie($this->cookieFactory->csrf($session->csrfToken, $session->accessTtl));

        return $response;
    }

    #[Route('/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookies->get('refresh_token', '');

        if ($refreshToken === '') {
            return new JsonResponse(['error' => 'Refresh token not provided.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->authService->refresh($refreshToken);

        $response = new JsonResponse([
            'user' => [
                'id' => $session->user->getId(),
                'name' => $session->user->getName(),
                'email' => $session->user->getEmail(),
                'role' => $session->user->getRole()->value,
            ],
        ], Response::HTTP_OK);

        $response->headers->setCookie($this->cookieFactory->access($session->accessToken, $session->accessTtl));
        $response->headers->setCookie($this->cookieFactory->refresh($session->refreshToken, $session->refreshTtl));
        $response->headers->setCookie($this->cookieFactory->csrf($session->csrfToken, $session->accessTtl));

        return $response;
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->cookies->get('refresh_token', '');

        if ($refreshToken !== '') {
            $this->authService->logout($refreshToken);
        }

        $response = new JsonResponse(['message' => 'Logged out successfully.'], Response::HTTP_OK);

        $response->headers->setCookie($this->cookieFactory->expired('access_token'));
        $response->headers->setCookie($this->cookieFactory->expired('refresh_token'));
        $response->headers->setCookie($this->cookieFactory->expired('csrf_token'));

        return $response;
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user === null) {
            return new JsonResponse(['error' => 'Not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'role' => $user->getRole()->value,
            ],
        ]);
    }
}
