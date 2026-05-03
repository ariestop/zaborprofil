<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Web;

use App\Module\Seo\Application\Service\RobotsTxtManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves a robots.txt response that depends on the current Symfony
 * environment.
 *
 * - prod  → allow indexing of public pages, block /admin/ and /api/, point
 *           to sitemap.xml
 * - other → block everything (dev, staging, test). Staging Nginx ALSO
 *           serves its own /robots.txt block as defence in depth.
 */
final readonly class RobotsController
{
    public function __construct(private RobotsTxtManager $robots)
    {
    }

    #[Route('/robots.txt', name: 'public_robots_txt', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response($this->robots->body(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
