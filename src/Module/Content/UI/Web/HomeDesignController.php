<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Главная dev2 по утверждённому визуальному макету, без зависимости от наполнения CMS. */
final class HomeDesignController extends AbstractController
{
    #[Route('/', name: 'home_design', methods: ['GET'], priority: 10)]
    public function __invoke(): Response
    {
        return $this->render('public/home/reference.html.twig');
    }
}
