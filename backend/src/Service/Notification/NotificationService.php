<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Markazlashtirilgan bildirishnomalar servisi.
 * Barcha notification yaratish shu yerdan o'tadi.
 */
final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Bitta foydalanuvchiga bildirishnoma yuboradi.
     */
    public function notify(
        User $user,
        NotificationType $type,
        string $title,
        string $message,
        ?string $linkUrl = null,
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setLinkUrl($linkUrl);
        $this->em->persist($notification);

        return $notification;
    }

    /**
     * Barcha admin foydalanuvchilarga bildirishnoma yuboradi.
     *
     * @return Notification[]
     */
    public function notifyAdmins(
        NotificationType $type,
        string $title,
        string $message,
        ?string $linkUrl = null,
    ): array {
        $admins = $this->em->getRepository(User::class)->findBy(['role' => UserRole::Admin]);
        $notifications = [];

        foreach ($admins as $admin) {
            $notifications[] = $this->notify($admin, $type, $title, $message, $linkUrl);
        }

        return $notifications;
    }

    /**
     * Barcha foydalanuvchilarga (admin + user) bildirishnoma yuboradi.
     *
     * @return Notification[]
     */
    public function notifyAllStaff(
        NotificationType $type,
        string $title,
        string $message,
        ?string $linkUrl = null,
    ): array {
        $staff = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.role IN (:roles)')
            ->setParameter('roles', [UserRole::Admin, UserRole::User])
            ->getQuery()
            ->getResult();

        $notifications = [];
        foreach ($staff as $user) {
            $notifications[] = $this->notify($user, $type, $title, $message, $linkUrl);
        }

        return $notifications;
    }

    /**
     * Bildirishnomalarni DBga yozadi (flush).
     */
    public function flush(): void
    {
        $this->em->flush();
    }
}
