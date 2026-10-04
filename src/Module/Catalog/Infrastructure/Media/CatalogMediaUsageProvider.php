<?php

declare(strict_types=1);

namespace App\Module\Catalog\Infrastructure\Media;

use App\Module\Media\Application\Usage\MediaPathExtractor;
use App\Module\Media\Application\Usage\MediaUsageProviderInterface;
use App\Module\Media\Application\Usage\MediaUsageReference;
use App\Module\Media\Application\Usage\MediaUsageRow;
use Doctrine\DBAL\Connection;

final readonly class CatalogMediaUsageProvider implements MediaUsageProviderInterface
{
    private const string LIKE = '%uploads%media%';

    public function __construct(private Connection $connection)
    {
    }

    public function references(): iterable
    {
        $products = $this->connection->fetchAllAssociative(
            'SELECT id, name, status, summary, description, og_image FROM catalog_products
             WHERE og_image LIKE :like OR summary LIKE :like OR description LIKE :like',
            ['like' => self::LIKE],
        );

        foreach ($products as $data) {
            $row = new MediaUsageRow($data);
            $id = $row->ulid('id');
            $fields = ['og_image' => 'OG-изображение', 'summary' => 'Краткое описание', 'description' => 'Описание'];
            foreach ($fields as $column => $location) {
                foreach (MediaPathExtractor::extract($row->nullableText($column)) as $path) {
                    yield new MediaUsageReference(
                        MediaUsageReference::TYPE_PRODUCT,
                        $id,
                        $path,
                        $row->text('name'),
                        'Товар: '.$location,
                        null,
                        $row->text('status'),
                    );
                }
            }
        }

        $categories = $this->connection->fetchAllAssociative(
            'SELECT id, title, is_active, description FROM catalog_categories WHERE description LIKE :like',
            ['like' => self::LIKE],
        );

        foreach ($categories as $data) {
            $row = new MediaUsageRow($data);
            foreach (MediaPathExtractor::extract($row->nullableText('description')) as $path) {
                yield new MediaUsageReference(
                    MediaUsageReference::TYPE_CATEGORY,
                    $row->ulid('id'),
                    $path,
                    $row->text('title'),
                    'Категория: описание',
                    null,
                    $row->flag('is_active') ? 'active' : 'inactive',
                );
            }
        }
    }
}
