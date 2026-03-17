<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;

final readonly class GetProductBySlugUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @return array|null
     */
    public function execute(string $slug): ?array
    {
        $product = $this->productRepository->findBySlug($slug);
        if (!$product) {
            return null;
        }

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'description' => $product->getDescription(),
            'category' => [
                'id' => $product->getCategory()->getId(),
                'name' => $product->getCategory()->getName(),
                'slug' => $product->getCategory()->getSlug(),
            ],
            'variants' => array_map(
                fn ($v) => [
                    'id' => $v->getId(),
                    'sku' => $v->getSku(),
                    'attributes' => $v->getAttributes(),
                ],
                $product->getVariants()->toArray()
            ),
        ];
    }
}
