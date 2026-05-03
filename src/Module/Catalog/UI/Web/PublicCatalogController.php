<?php

declare(strict_types=1);

namespace App\Module\Catalog\UI\Web;

use App\Module\Catalog\Domain\Entity\Category;
use App\Module\Catalog\Domain\Entity\Product;
use App\Module\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Module\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Module\Seo\Application\Service\SchemaOrgBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicCatalogController extends AbstractController
{
    #[Route('/catalog/', name: 'catalog_public_index', priority: 20, methods: ['GET'])]
    public function index(
        CategoryRepositoryInterface $categories,
        ProductRepositoryInterface $products,
        UrlGeneratorInterface $urlGenerator,
        SchemaOrgBuilder $schemaOrg,
        #[Autowire('%app.site_url%')]
        string $siteUrl,
    ): Response {
        $canonical = $urlGenerator->generate('catalog_public_index', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $breadcrumbs = [
            ['label' => 'Главная', 'path' => '/', 'current' => false],
            ['label' => 'Каталог', 'path' => '/catalog/', 'current' => true],
        ];

        return $this->render('public/catalog/index.html.twig', [
            'categories' => $categories->findActiveForPublic(),
            'products' => $products->findPublishedIndexableSlice(100, 0),
            'meta_description' => 'Каталог продукции ЗаборПрофиль.',
            'canonical_url' => $canonical,
            'og_title' => 'Каталог',
            'og_description' => 'Каталог продукции ЗаборПрофиль.',
            'breadcrumbs' => $breadcrumbs,
            'json_ld_blocks' => [
                $schemaOrg->breadcrumbList($breadcrumbs, $siteUrl),
            ],
        ]);
    }

    #[Route('/catalog/{path}', name: 'catalog_public_show', requirements: ['path' => '.+'], priority: 20, methods: ['GET'])]
    public function show(
        string $path,
        CategoryRepositoryInterface $categories,
        ProductRepositoryInterface $products,
        UrlGeneratorInterface $urlGenerator,
        SchemaOrgBuilder $schemaOrg,
        #[Autowire('%app.site_url%')]
        string $siteUrl,
    ): Response {
        $catalogPath = $this->catalogPath($path);
        $product = $products->findPublishedByPath($catalogPath);

        if ($product instanceof Product) {
            return $this->productResponse($product, $urlGenerator, $schemaOrg, $siteUrl);
        }

        $category = $categories->findActiveByPath($catalogPath);
        if (!$category instanceof Category) {
            return $this->notFoundResponse();
        }

        return $this->categoryResponse($category, $products, $urlGenerator, $schemaOrg, $siteUrl);
    }

    private function productResponse(Product $product, UrlGeneratorInterface $urlGenerator, SchemaOrgBuilder $schemaOrg, string $siteUrl): Response
    {
        $canonical = $product->canonicalUrl()
            ?? $urlGenerator->generate('catalog_public_show', ['path' => $this->routePath($product->path())], UrlGeneratorInterface::ABSOLUTE_URL);
        $breadcrumbs = $this->breadcrumbsForProduct($product);
        $description = $product->metaDescription() ?? $product->summary();

        return $this->render('public/catalog/product.html.twig', [
            'product' => $product,
            'variants' => $product->activeVariants(),
            'meta_description' => $description,
            'meta_robots' => $product->isIndexable() ? 'index, follow' : 'noindex, nofollow',
            'canonical_url' => $canonical,
            'og_type' => 'product',
            'og_title' => $product->ogTitle() ?? $product->name(),
            'og_description' => $product->ogDescription() ?? $description,
            'og_image' => $product->ogImage(),
            'breadcrumbs' => $breadcrumbs,
            'json_ld_blocks' => [
                $schemaOrg->breadcrumbList($breadcrumbs, $siteUrl),
                $schemaOrg->product($product, $canonical),
            ],
        ]);
    }

    private function categoryResponse(Category $category, ProductRepositoryInterface $products, UrlGeneratorInterface $urlGenerator, SchemaOrgBuilder $schemaOrg, string $siteUrl): Response
    {
        $canonical = $urlGenerator->generate('catalog_public_show', ['path' => $this->routePath($category->path())], UrlGeneratorInterface::ABSOLUTE_URL);
        $breadcrumbs = $this->breadcrumbsForCategory($category);

        return $this->render('public/catalog/category.html.twig', [
            'category' => $category,
            'products' => $products->findPublishedByCategory($category),
            'meta_description' => $category->description(),
            'canonical_url' => $canonical,
            'og_title' => $category->title(),
            'og_description' => $category->description(),
            'breadcrumbs' => $breadcrumbs,
            'json_ld_blocks' => [
                $schemaOrg->breadcrumbList($breadcrumbs, $siteUrl),
            ],
        ]);
    }

    private function catalogPath(string $path): string
    {
        return '/catalog/'.trim($path, '/').'/';
    }

    private function routePath(string $catalogPath): string
    {
        return trim(preg_replace('#^/catalog/#', '', $catalogPath) ?? $catalogPath, '/');
    }

    /**
     * @return list<array{label: string, path: string, current: bool}>
     */
    private function breadcrumbsForCategory(Category $category): array
    {
        return [
            ['label' => 'Главная', 'path' => '/', 'current' => false],
            ['label' => 'Каталог', 'path' => '/catalog/', 'current' => false],
            ['label' => $category->title(), 'path' => $category->path(), 'current' => true],
        ];
    }

    /**
     * @return list<array{label: string, path: string, current: bool}>
     */
    private function breadcrumbsForProduct(Product $product): array
    {
        $breadcrumbs = [
            ['label' => 'Главная', 'path' => '/', 'current' => false],
            ['label' => 'Каталог', 'path' => '/catalog/', 'current' => false],
        ];

        if ($product->category() instanceof Category) {
            $breadcrumbs[] = [
                'label' => $product->category()->title(),
                'path' => $product->category()->path(),
                'current' => false,
            ];
        }

        $breadcrumbs[] = [
            'label' => $product->name(),
            'path' => $product->path(),
            'current' => true,
        ];

        return $breadcrumbs;
    }

    private function notFoundResponse(): Response
    {
        $response = $this->render('public/errors/not_found.html.twig');
        $response->setStatusCode(Response::HTTP_NOT_FOUND);
        $response->headers->set('X-Robots-Tag', 'noindex,nofollow');

        return $response;
    }
}
