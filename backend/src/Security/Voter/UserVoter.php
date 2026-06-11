<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * RBAC voter for User entity operations.
 *
 * All user management actions (view, create, update, delete, reset_password)
 * are restricted to admin role only.
 */
final class UserVoter extends Voter
{
    public const VIEW = 'USER_VIEW';
    public const CREATE = 'USER_CREATE';
    public const UPDATE = 'USER_UPDATE';
    public const DELETE = 'USER_DELETE';
    public const RESET_PASSWORD = 'USER_RESET_PASSWORD';

    private const SUPPORTED_ATTRIBUTES = [
        self::VIEW,
        self::CREATE,
        self::UPDATE,
        self::DELETE,
        self::RESET_PASSWORD,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // All user management operations require admin role
        return $user->getRole() === UserRole::Admin;
    }
}
