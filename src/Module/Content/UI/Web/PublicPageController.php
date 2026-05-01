<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Web;

use App\Module\Content\Application\Service\PublicPageResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicPageController extends AbstractController
{
    #[Route('/{path}', name: 'content_public_page', requirements: ['path' => '.*'], priority: -100, methods: ['GET'])]
    public function __invoke(string $path, PublicPageResolver $resolver, TwigBlockRenderer $blockRenderer): Response
    {
        $page = $resolver->resolve($path);

        if ($page === null) {
            throw $this->createNotFoundException('Page not found.');
        }

        $blocks = [];
        foreach ($page->blocks as $block) {
            $blocks[] = $blockRenderer->render($block);
        }

        return $this->render('public/page/show.html.twig', [
            'page' => $page,
            'blocks' => $blocks,
        ]);
    }
}
