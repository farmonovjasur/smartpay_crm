<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Dashboard\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/api/dashboard/stats', name: 'dashboard_stats', methods: ['GET'])]
    public function stats(DashboardService $dashboardService): JsonResponse
    {
        return new JsonResponse($dashboardService->stats());
    }
}
