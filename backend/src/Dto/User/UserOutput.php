<?php

declare(strict_types=1);

namespace App\Dto\User;

use App\Entity\User;

final class UserOutput
{
    public int $id;
    public string $name;
    public string $email;
    public string $role;
    public bool $isActive;
    public ?string $lastLoginAt;
    public string $createdAt;

    public static function fromEntity(User $user): self
    {
        $dto = new self();
        $dto->id = $user->getId();
        $dto->name = $user->getName();
        $dto->email = $user->getEmail();
        $dto->role = $user->getRole()->value;
        $dto->isActive = $user->isActive();
        $dto->lastLoginAt = $user->getLastLoginAt()?->format('c');
        $dto->createdAt = $user->getCreatedAt()->format('c');

        return $dto;
    }
}
