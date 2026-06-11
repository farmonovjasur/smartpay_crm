<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * RBAC voter for Invoice entity operations.
 *
 * - view, create, download: admin and user
 * - delete: admin only
 */
final class InvoiceVoter extends Voter
{
    public const VIEW = 'INVOICE_VIEW';
    public const CREATE = 'INVOICE_CREATE';
    public const DOWNLOAD = 'INVOICE_DOWNLOAD';
    public const DELETE = 'INVOICE_DELETE';

    private const SUPPORTED_ATTRIBUTES = [
        self::VIEW,
        self::CREATE,
        self::DOWNLOAD,
        self::DELETE,
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
