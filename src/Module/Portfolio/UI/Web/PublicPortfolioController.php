<?php

declare(strict_types=1);

namespace App\Module\Portfolio\UI\Web;

use App\Module\Content\UI\Web\PublicPageHttpCache;
use App\Module\Portfolio\Application\Query\PublicPortfolioQuery;
use App\Module\Portfolio\Domain\Entity\PortfolioCategory;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use App\Module\Seo\Application\Service\SchemaOrgBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Портфолио на сайте, три уровня под главной: /portfolio/ → /portfolio/<раздел>/ → /portfolio/<раздел>/<работа>/.
 */
final class PublicPortfolioController extends AbstractController
{
    private const string SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    private const int LATEST_ON_HUB = 6;

    public function __construct(
        private readonly PublicPortfolioQuery $portfolio,
        private readonly SchemaOrgBuilder $schemaOrg,
        private readonly PublicPageHttpCache $httpCache,
        #[Autowire('%app.site_url%')]
        private readonly string $siteUrl,
    ) {
    }

    #[Route('/portfolio/', name: 'portfolio_public_index', priority: 20, methods: ['GET', 'HEAD'])]
    public function index(Request $request): Response
    {
        $breadcrumbs = [
            ['label' => 'Главная', 'path' => '/', 'current' => false],
            ['label' => 'Портфолио', 'path' => '/portfolio/', 'current' => true],
        ];
        $description = 'Фото и видео заборов, которые мы построили в Саратове и области: размеры, материалы, сроки монтажа.';

        return $this->cached($request, $this->render('public/portfolio/index.html.twig', [
            'categories' => $this->portfolio->categoryCards(),
            'latest' => $this->portfolio->latest(self::LATEST_ON_HUB),
            'total' => $this->portfolio->totalPublished(),
            'meta_description' => $description,
            'canonical_url' => $this->absolute('/portfolio/'),
            'og_title' => 'Портфолио — наши работы',
            'og_description' => $description,
            'breadcrumbs' => $breadcrumbs,
            'json_ld_blocks' => [$this->schemaOrg->breadcrumbList($breadcrumbs, $this->siteUrl)],
        ]));
    }

    #[Route('/portfolio/{category}/', name: 'portfolio_public_category', requirements: ['category' => self::SLUG], priority: 20, methods: ['GET', 'HEAD'])]
    public function category(string $category, Request $request): Response
    {
        $section = $this->portfolio->category($category);
        $projects = $section instanceof PortfolioCategory ? $this->portfolio->projectsOf($section) : [];
        if (!$section instanceof PortfolioCategory || $projects === []) {
            return $this->notFound();
        }

        $breadcrumbs = [
            ['label' => 'Главная', 'path' => '/', 'current' => false],
            ['label' => 'Портфолио', 'path' => '/portfolio/', 'current' => false],
            ['label' => $section->title(), 'path' => $section->path(), 'current' => true],
        ];
        $description = $section->description() ?? \sprintf('%s: фото и видео наших работ в Саратове и области.', $section->title());

        return $this->cached($request, $this->render('public/portfolio/category.html.twig', [
            'category' => $section,
            'projects' => $projects,
            'categories' => $this->portfolio->categoryCards(),
            'total' => $this->portfolio->totalPublished(),
            'meta_description' => $description,
            'canonical_url' => $this->absolute($section->path()),
            'og_title' => $section->title().' — портфолио',
            'og_description' => $description,
            'og_image' => $this->absoluteOrNull($projects[0]->cover()?->src()),
            'breadcrumbs' => $breadcrumbs,
            'json_ld_blocks' => [$this->schemaOrg->breadcrumbList($breadcrumbs, $this->siteUrl)],
        ]));
    }

    #[Route('/portfolio/{category}/{slug}/', name: 'portfolio_public_project', requirements: ['category' => self::SLUG, 'slug' => self::SLUG], priority: 20, methods: ['GET', 'HEAD'])]
    public function project(string $category, string $slug, Request $request): Response
    {
        $section = $this->portfolio->category($category);
        $project = $section instanceof PortfolioCategory ? $this->portfolio->project($section, $slug) : null;
        if (!$section instanceof PortfolioCategory || !$project instanceof PortfolioProject) {
            return $this->notFound();
        }

        $breadcrumbs = [
            ['label' => 'Главная', 'path' => '/', 'current' => false],
            ['label' => 'Портфолио', 'path' => '/portfolio/', 'current' => false],
            ['label' => $section->title(), 'path' => $section->path(), 'current' => false],
            ['label' => $project->title(), 'path' => $project->path(), 'current' => true],
        ];
        $description = $project->seoDescription() ?? $project->summary() ?? \sprintf('%s — фото и видео работы.', $project->title());
        $canonical = $this->absolute($project->path());

        return $this->cached($request, $this->render('public/portfolio/project.html.twig', [
            'project' => $project,
            'category' => $section,
            'neighbours' => $this->portfolio->neighbours($project),
            'page_title' => $project->seoTitle() ?? $project->title(),
            'meta_description' => $description,
            'canonical_url' => $canonical,
            'og_type' => 'article',
            'og_title' => $project->title(),
            'og_description' => $description,
            'og_image' => $this->absoluteOrNull($project->cover()?->src()),
            'breadcrumbs' => $breadcrumbs,
            'json_ld_blocks' => [
                $this->schemaOrg->breadcrumbList($breadcrumbs, $this->siteUrl),
                $this->creativeWork($project, $canonical, $description),
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function creativeWork(PortfolioProject $project, string $canonical, string $description): array
    {
        $images = [];
        foreach ($project->photos() as $photo) {
            $images[] = $this->absolute($photo->src());
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $project->title(),
            'description' => $description,
            'url' => $canonical,
            'image' => $images,
        ];
        if ($project->year() !== null) {
            $schema['dateCreated'] = (string) $project->year();
        }
        if ($project->place() !== null) {
            $schema['locationCreated'] = ['@type' => 'Place', 'name' => $project->place()];
        }

        return $schema;
    }

    private function cached(Request $request, Response $response): Response
    {
        $this->httpCache->apply($response, $request);

        return $response;
    }

    private function absolute(string $path): string
    {
        return rtrim($this->siteUrl, '/').'/'.ltrim($path, '/');
    }

    private function absoluteOrNull(?string $path): ?string
    {
        return $path === null ? null : $this->absolute($path);
    }

    private function notFound(): Response
    {
        $response = $this->render('public/errors/not_found.html.twig');
        $response->setStatusCode(Response::HTTP_NOT_FOUND);
        $response->headers->set('X-Robots-Tag', 'noindex,nofollow');

        return $response;
    }
}
