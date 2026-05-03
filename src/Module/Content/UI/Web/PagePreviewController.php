<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Web;

use App\Module\Content\Application\Service\PageBlockView;
use App\Module\Content\Application\Service\PagePreviewToken;
use App\Module\Content\Application\Service\PublicPageView;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\Application\Logging\BusinessEventLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PagePreviewController extends AbstractController
{
    #[Route('/_preview/content/pages/{id}/{token}', name: 'content_page_preview', methods: ['GET'])]
    public function __invoke(
        string $id,
        string $token,
        PagePreviewToken $previewToken,
        PageRepositoryInterface $pages,
        TwigBlockRenderer $blockRenderer,
        UrlGeneratorInterface $urlGenerator,
        BusinessEventLogger $businessEvents,
    ): Response {
        if (!$previewToken->isValid($id, $token)) {
            throw $this->createNotFoundException('Preview not found.');
        }

        $page = $pages->findPreviewById($id);
        if ($page === null) {
            throw $this->createNotFoundException('Preview not found.');
        }

        $businessEvents->log('page.previewViewed', [
            'page_id' => $id,
            'path' => $page->path(),
        ]);

        $blocks = [];
        foreach ($page->enabledBlocks() as $block) {
            $blocks[] = $blockRenderer->render(PageBlockView::fromBlock($block));
        }

        $canonical = $page->canonicalUrl()
            ?? $urlGenerator->generate('content_public_page', ['path' => ltrim($page->path(), '/')], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = $this->render('public/page/show.html.twig', [
            'page' => PublicPageView::fromPage($page),
            'blocks' => $blocks,
            'meta_description' => $page->metaDescription(),
            'meta_robots' => 'noindex, nofollow',
            'canonical_url' => $canonical,
            'og_type' => $page->ogType(),
            'og_title' => $page->ogTitle(),
            'og_description' => $page->ogDescription() ?? $page->metaDescription(),
            'og_image' => $page->ogImage(),
            'json_ld_blocks' => $page->jsonLd(),
            'is_preview' => true,
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex,nofollow');

        return $response;
    }
}
