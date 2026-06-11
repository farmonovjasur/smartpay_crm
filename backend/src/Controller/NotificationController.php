<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'notification_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = 20;

        $qb = $this->em->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC');

        if (filter_var($request->query->get('unread_only', 'false'), FILTER_VALIDATE_BOOLEAN)) {
            $qb->andWhere('n.isRead = false');
        }

        $total = (int) (clone $qb)->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();

        $notifications = $qb->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (Notification $n) => [
            'id' => $n->getId(),
            'type' => $n->getType()->value,
            'title' => $n->getTitle(),
            'message' => $n->getMessage(),
            'link_url' => $n->getLinkUrl(),
            'is_read' => $n->isRead(),
            'created_at' => $n->getCreatedAt()->format('c'),
        ], $notifications);

        return new JsonResponse(['data' => $data, 'total' => $total, 'page' => $page]);
    }

    #[Route('/{id}/read', name: 'notification_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markRead(int $id): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $notification = $this->em->getRepository(Notification::class)->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if ($notification === null) {
            throw new NotFoundHttpException('Notification not found.');
        }

        $notification->markAsRead();
        $this->em->flush();

        return new Response('', 204);
    }

    #[Route('/read-all', name: 'notification_read_all', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $count = (int) $this->em->getConnection()->executeStatement(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0',
            [$user->getId()]
        );

        return new JsonResponse(['markedCount' => $count]);
    }

    #[Route('/{id}', name: 'notification_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $notification = $this->em->getRepository(Notification::class)->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if ($notification === null) {
            throw new NotFoundHttpException('Notification not found.');
        }

        $this->em->remove($notification);
        $this->em->flush();

        return new Response('', 204);
    }

    #[Route('/delete-all-read', name: 'notification_delete_read', methods: ['POST'])]
    public function deleteAllRead(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $count = (int) $this->em->getConnection()->executeStatement(
            'DELETE FROM notifications WHERE user_id = ? AND is_read = 1',
            [$user->getId()]
        );

        return new JsonResponse(['deletedCount' => $count]);
    }
}
