<?php

declare(strict_types=1);

namespace App\Module\Catalog\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Catalog\Domain\Entity\Category;
use App\Module\Catalog\Domain\Entity\Product;
use App\Module\Catalog\Domain\Entity\Variant;
use App\Module\Catalog\Domain\Enum\ProductStatus;
use App\Module\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Module\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Module\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Module\Catalog\Domain\Repository\VariantRepositoryInterface;
use App\Module\Content\UI\Admin\JsonRequest;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;
use ValueError;

#[Route('/admin/api/catalog')]
final readonly class CatalogApiController
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private ProductRepositoryInterface $products,
        private VariantRepositoryInterface $variants,
        private JsonRequest $jsonRequest,
        private AuthorizationCheckerInterface $authorizationChecker,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/categories', name: 'admin_api_catalog_categories_index', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        if (!$this->canView()) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            'categories' => array_map(static fn (Category $category): array => $category->toArray(), $this->categories->findAllForAdmin()),
        ]);
    }

    #[Route('/categories', name: 'admin_api_catalog_categories_create', methods: ['POST'])]
    public function createCategory(Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $category = new Category(
                $this->jsonRequest->string($payload, 'title'),
                $this->jsonRequest->string($payload, 'slug'),
                $this->jsonRequest->string($payload, 'path'),
                $this->jsonRequest->nullableString($payload, 'description'),
                $this->jsonRequest->int($payload, 'sortOrder', 0),
                $this->jsonRequest->bool($payload, 'isActive', true),
                $this->categories->findById($this->jsonRequest->nullableString($payload, 'parentId')),
            );
            $this->categories->save($category);

            return new JsonResponse($category->toArray(), 201);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    #[Route('/categories/{id}', name: 'admin_api_catalog_categories_update', methods: ['PUT'])]
    public function updateCategory(string $id, Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $category = $this->categories->get($id);
            $category->update(
                $this->jsonRequest->string($payload, 'title'),
                $this->jsonRequest->string($payload, 'slug'),
                $this->jsonRequest->string($payload, 'path'),
                $this->jsonRequest->nullableString($payload, 'description'),
                $this->jsonRequest->int($payload, 'sortOrder', 0),
                $this->jsonRequest->bool($payload, 'isActive', true),
                $this->categories->findById($this->jsonRequest->nullableString($payload, 'parentId')),
            );
            $this->categories->save($category);

            return new JsonResponse($category->toArray());
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    #[Route('/categories/{id}', name: 'admin_api_catalog_categories_delete', methods: ['DELETE'])]
    public function deleteCategory(string $id): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->accessDenied();
        }

        try {
            $this->categories->remove($this->categories->get($id));

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    #[Route('/products', name: 'admin_api_catalog_products_index', methods: ['GET'])]
    public function products(): JsonResponse
    {
        if (!$this->canView()) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            'products' => array_map(static fn (Product $product): array => $product->toArray(), $this->products->findAllForAdmin()),
            'statuses' => array_map(static fn (ProductStatus $status): string => $status->value, ProductStatus::cases()),
        ]);
    }

    #[Route('/products', name: 'admin_api_catalog_products_create', methods: ['POST'])]
    public function createProduct(Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $product = new Product(
                $this->jsonRequest->string($payload, 'name'),
                $this->jsonRequest->string($payload, 'slug'),
                $this->jsonRequest->string($payload, 'path'),
                ProductStatus::from($this->jsonRequest->string($payload, 'status', ProductStatus::Draft->value)),
                $this->jsonRequest->nullableString($payload, 'summary'),
                $this->jsonRequest->nullableString($payload, 'description'),
                $this->jsonRequest->bool($payload, 'isIndexable', true),
                $this->categories->findById($this->jsonRequest->nullableString($payload, 'categoryId')),
            );
            $this->products->save($product);

            return new JsonResponse($product->toArray(), 201);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    #[Route('/products/{id}', name: 'admin_api_catalog_products_update', methods: ['PUT'])]
    public function updateProduct(string $id, Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $product = $this->products->get($id);
            $product->update(
                $this->jsonRequest->string($payload, 'name'),
                $this->jsonRequest->string($payload, 'slug'),
                $this->jsonRequest->string($payload, 'path'),
                ProductStatus::from($this->jsonRequest->string($payload, 'status', ProductStatus::Draft->value)),
                $this->jsonRequest->nullableString($payload, 'summary'),
                $this->jsonRequest->nullableString($payload, 'description'),
                $this->jsonRequest->bool($payload, 'isIndexable', true),
                $this->categories->findById($this->jsonRequest->nullableString($payload, 'categoryId')),
            );
            $this->products->save($product);

            return new JsonResponse($product->toArray());
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    #[Route('/products/{id}', name: 'admin_api_catalog_products_delete', methods: ['DELETE'])]
    public function deleteProduct(string $id): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->accessDenied();
        }

        try {
            $this->products->remove($this->products->get($id));

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    #[Route('/products/{productId}/variants', name: 'admin_api_catalog_variants_create', methods: ['POST'])]
    public function createVariant(string $productId, Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $variant = new Variant(
                $this->products->get($productId),
                $this->jsonRequest->string($payload, 'sku'),
                $this->jsonRequest->string($payload, 'title'),
                $this->jsonRequest->int($payload, 'priceCents', 0),
                $this->jsonRequest->string($payload, 'currency', 'RUB'),
                $this->jsonRequest->int($payload, 'sortOrder', 0),
                $this->jsonRequest->bool($payload, 'isActive', true),
            );
            $this->variants->save($variant);

            return new JsonResponse($variant->toArray(), 201);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    #[Route('/variants/{id}', name: 'admin_api_catalog_variants_update', methods: ['PUT'])]
    public function updateVariant(string $id, Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $variant = $this->variants->get($id);
            $variant->update(
                $this->jsonRequest->string($payload, 'sku'),
                $this->jsonRequest->string($payload, 'title'),
                $this->jsonRequest->int($payload, 'priceCents', 0),
                $this->jsonRequest->string($payload, 'currency', 'RUB'),
                $this->jsonRequest->int($payload, 'sortOrder', 0),
                $this->jsonRequest->bool($payload, 'isActive', true),
            );
            $this->variants->save($variant);

            return new JsonResponse($variant->toArray());
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    #[Route('/variants/{id}', name: 'admin_api_catalog_variants_delete', methods: ['DELETE'])]
    public function deleteVariant(string $id): JsonResponse
    {
        if (!$this->canManage()) {
            return $this->accessDenied();
        }

        try {
            $this->variants->remove($this->variants->get($id));

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    private function canView(): bool
    {
        return $this->authorizationChecker->isGranted(AdminPermission::CATALOG_VIEW);
    }

    private function canManage(): bool
    {
        return $this->authorizationChecker->isGranted(AdminPermission::CATALOG_MANAGE);
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }

    private function error(Throwable $exception): JsonResponse
    {
        if ($exception instanceof CatalogNotFoundException) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }

        if ($exception instanceof InvalidArgumentException || $exception instanceof ValueError) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
                'code' => 'VALIDATION',
            ], 422);
        }

        $this->logger->error('Admin Catalog API failed with an unexpected exception.', [
            'exception' => $exception,
        ]);

        return new JsonResponse([
            'error' => 'Internal server error',
            'code' => 'INTERNAL',
        ], 500);
    }
}
