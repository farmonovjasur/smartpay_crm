<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\User\UserCreateInput;
use App\Dto\User\UserUpdateInput;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\RefreshTokenStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RefreshTokenStore $refreshTokenStore,
        private readonly AuditLogger $auditLogger,
        private readonly PasswordGenerator $passwordGenerator,
    ) {
    }

    public function create(UserCreateInput $in, User $actor): User
    {
        // Check email uniqueness
        $existing = $this->userRepository->findOneBy(['email' => $in->email]);
        if ($existing !== null) {
            throw new ConflictHttpException('Email already exists.');
        }

        $user = new User();
        $user->setName($in->name);
        $user->setEmail($in->email);
        $user->setRole(UserRole::from($in->role));
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $in->password));

        $this->em->persist($user);
        $this->em->flush();

        $this->auditLogger->log($actor, 'user.created', 'user', $user->getId(), [
            'email' => $user->getEmail(),
            'role' => $user->getRole()->value,
        ]);

        return $user;
    }

    public function update(int $id, UserUpdateInput $in, User $actor): User
    {
        $user = $this->findOrFail($id);

        // Check email uniqueness (if changed)
        if ($user->getEmail() !== $in->email) {
            $existing = $this->userRepository->findOneBy(['email' => $in->email]);
            if ($existing !== null) {
                throw new ConflictHttpException('Email already exists.');
            }
        }

        $user->setName($in->name);
        $user->setEmail($in->email);
        $user->setRole(UserRole::from($in->role));
        $user->setIsActive($in->isActive);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        $this->auditLogger->log($actor, 'user.updated', 'user', $user->getId(), [
            'email' => $user->getEmail(),
            'role' => $user->getRole()->value,
        ]);

        return $user;
    }

    public function softDelete(int $id, User $actor): void
    {
        $user = $this->findOrFail($id);

        $user->softDelete();
        $this->em->flush();

        // Revoke all refresh tokens for the deleted user
        $this->refreshTokenStore->revokeAllForUser($user);

        $this->auditLogger->log($actor, 'user.deleted', 'user', $user->getId(), [
            'email' => $user->getEmail(),
        ]);
    }

    /**
     * Reset a user's password. If $newPlain is null, generates a strong random password.
     *
     * @return string The plain-text password (for returning to admin)
     */
    public function resetPassword(int $id, ?string $newPlain, User $actor): string
    {
        $user = $this->findOrFail($id);

        if ($newPlain === null || $newPlain === '') {
            $newPlain = $this->passwordGenerator->generate();
        }

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $newPlain));
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        // Revoke all refresh tokens to force re-login
        $this->refreshTokenStore->revokeAllForUser($user);

        $this->auditLogger->log($actor, 'user.password_reset', 'user', $user->getId(), [
            'email' => $user->getEmail(),
        ]);

        return $newPlain;
    }

    public function findById(int $id): User
    {
        return $this->findOrFail($id);
    }

    /**
     * @return array{items: User[], total: int}
     */
    public function findPaginated(int $page = 1, int $pageSize = 20, ?string $search = null): array
    {
        $qb = $this->userRepository->createQueryBuilder('u');

        if ($search !== null && $search !== '') {
            $qb->andWhere('u.name LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('u.id', 'DESC');

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        // Paginate
        $qb->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        $items = $qb->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    private function findOrFail(int $id): User
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        return $user;
    }
}
