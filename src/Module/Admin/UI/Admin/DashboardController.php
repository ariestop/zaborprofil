<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_index', methods: ['GET'])]
    #[Route('/admin/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    #[Route('/admin/{path}', name: 'admin_spa', requirements: ['path' => '(?!(api|login|logout)(/|$)).*'], priority: 10, methods: ['GET'])]
    public function __invoke(string $path = ''): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }
}
