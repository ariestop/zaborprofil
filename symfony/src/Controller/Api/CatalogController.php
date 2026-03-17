<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Catalog\Application\UseCase\GetCatalogUseCase;
use App\Catalog\Application\UseCase\GetProductBySlugUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class CatalogController extends AbstractController
{
    public function __construct(
        private readonly GetCatalogUseCase $getCatalogUseCase,
        private readonly GetProductBySlugUseCase $getProductBySlugUseCase,
    ) {
    }

    #[Route('/catalog', name: 'api_catalog', methods: ['GET'])]
    public function catalog(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $result = $this->getCatalogUseCase->execute($page, $limit);

        return new JsonResponse($result);
    }

    #[Route('/catalog/categories', name: 'api_catalog_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        $result = $this->getCatalogUseCase->execute(1, 1);
        return new JsonResponse(['categories' => $result['categories']]);
    }

    #[Route('/product/{slug}', name: 'api_product', requirements: ['slug' => '[a-z0-9\-]+'], methods: ['GET'])]
    public function product(string $slug): JsonResponse|Response
    {
        $product = $this->getProductBySlugUseCase->execute($slug);

        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($product);
    }
}
