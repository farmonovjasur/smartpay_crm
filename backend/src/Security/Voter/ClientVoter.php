<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * RBAC voter for Client entity operations.
 *
 * - view, create, update, import, export, mark_paid: admin and user
 * - delete: admin only
 */
final class ClientVoter extends Voter
{
    public const VIEW = 'CLIENT_VIEW';
    public const CREATE = 'CLIENT_CREATE';
    public const UPDATE = 'CLIENT_UPDATE';
    public const DELETE = 'CLIENT_DELETE';
    public const IMPORT = 'CLIENT_IMPORT';
    public const EXPORT = 'CLIENT_EXPORT';
    public const MARK_PAID = 'CLIENT_MARK_PAID';
    public const PREPAY = 'CLIENT_PREPAY';

    private const SUPPORTED_ATTRIBUTES = [
        self::VIEW,
        self::CREATE,
        self::UPDATE,
        self::DELETE,
        self::IMPORT,
        self::EXPORT,
        self::MARK_PAID,
        self::PREPAY,
    ];

    private const ADMIN_ONLY = [
        self::DELETE,
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

        // Admin-only operations
        if (in_array($attribute, self::ADMIN_ONLY, true)) {
            return $user->getRole() === UserRole::Admin;
        }

        // Both admin and user roles have access
        return in_array($user->getRole(), [UserRole::Admin, UserRole::User], true);
    }
}
