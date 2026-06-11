<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class JwtCookieAuthenticator extends AbstractAuthenticator
{
    private const COOKIE_NAME = 'access_token';

    public function __construct(
        private readonly JWTEncoderInterface $jwtEncoder,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->cookies->has(self::COOKIE_NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $jwt = $request->cookies->get(self::COOKIE_NAME, '');

        if ($jwt === '') {
            throw new CustomUserMessageAuthenticationException('Access token cookie not found.');
        }

        try {
            $payload = $this->jwtEncoder->decode($jwt);
        } catch (JWTDecodeFailureException $e) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired token.', [], 0, $e);
        }

        if (!is_array($payload) || !isset($payload['sub'])) {
            throw new CustomUserMessageAuthenticationException('Invalid token payload.');
        }

        $userId = (int) $payload['sub'];

        return new SelfValidatingPassport(
            new UserBadge((string) $userId, function (string $identifier) {
                $user = $this->userRepository->find((int) $identifier);

                if ($user === null || $user->isDeleted() || !$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('User not found or inactive.');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the request continue to the controller
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
