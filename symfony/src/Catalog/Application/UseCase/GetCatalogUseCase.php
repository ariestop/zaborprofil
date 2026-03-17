<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase;

use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;

final readonly class GetCatalogUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @return array{categories: array, products: array, total: int, page: int, limit: int}
     */
    public function execute(int $page = 1, int $limit = 20): array
    {
        $categories = $this->categoryRepository->findAll();
        $products = $this->productRepository->findAllWithCategories($page, $limit);
        $total = $this->productRepository->countAll();

        return [
            'categories' => array_map(
                fn ($c) => ['id' => $c->getId(), 'name' => $c->getName(), 'slug' => $c->getSlug()],
                $categories
            ),
            'products' => array_map(
                fn ($p) => [
                    'id' => $p->getId(),
                    'name' => $p->getName(),
                    'slug' => $p->getSlug(),
                    'description' => $p->getDescription(),
                    'category' => [
                        'id' => $p->getCategory()->getId(),
                        'name' => $p->getCategory()->getName(),
                        'slug' => $p->getCategory()->getSlug(),
                    ],
                    'variants' => array_map(
                        fn ($v) => [
                            'id' => $v->getId(),
                            'sku' => $v->getSku(),
                            'attributes' => $v->getAttributes(),
                        ],
                        $p->getVariants()->toArray()
                    ),
                ],
                $products
            ),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
