<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Web;

use App\Module\Content\Application\Service\PublicPageResolverInterface;
use App\Module\Seo\Application\Service\SchemaOrgBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicPageController extends AbstractController
{
    #[Route('/{path}', name: 'content_public_page', requirements: ['path' => '.*'], priority: -100, methods: ['GET'])]
    public function __invoke(
        string $path,
        PublicPageResolverInterface $resolver,
        TwigBlockRenderer $blockRenderer,
        UrlGeneratorInterface $urlGenerator,
        SchemaOrgBuilder $schemaOrg,
    ): Response {
        $page = $resolver->resolve($path);

        if ($page === null) {
            throw $this->createNotFoundException('Page not found.');
        }

        $blocks = [];
        foreach ($page->blocks as $block) {
            $blocks[] = $blockRenderer->render($block);
        }

        $canonical = $page->canonicalUrl
            ?? $urlGenerator->generate('content_public_page', ['path' => ltrim($page->path, '/')], UrlGeneratorInterface::ABSOLUTE_URL);
        $jsonLdBlocks = [
            $schemaOrg->webPage($page, $canonical),
            ...($page->jsonLd ?? []),
        ];

        return $this->render('public/page/show.html.twig', [
            'page' => $page,
            'blocks' => $blocks,
            'meta_description' => $page->metaDescription,
            'meta_robots' => $page->isIndexable ? 'index, follow' : 'noindex, nofollow',
            'canonical_url' => $canonical,
            'og_type' => $page->ogType,
            'og_title' => $page->ogTitle,
            'og_description' => $page->ogDescription ?? $page->metaDescription,
            'og_image' => $page->ogImage,
            'json_ld_blocks' => $jsonLdBlocks,
        ]);
    }
}
