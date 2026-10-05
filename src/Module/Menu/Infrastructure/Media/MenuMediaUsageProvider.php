<?php

declare(strict_types=1);

namespace App\Module\Menu\Infrastructure\Media;

use App\Module\Media\Application\Usage\MediaPathExtractor;
use App\Module\Media\Application\Usage\MediaUsageProviderInterface;
use App\Module\Media\Application\Usage\MediaUsageReference;
use App\Module\Media\Application\Usage\MediaUsageRow;
use Doctrine\DBAL\Connection;

final readonly class MenuMediaUsageProvider implements MediaUsageProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function references(): iterable
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, position, label, url, is_active FROM menu_items WHERE url LIKE :like',
            ['like' => '%uploads%media%'],
        );

        foreach ($rows as $data) {
            $row = new MediaUsageRow($data);
            foreach (MediaPathExtractor::extract($row->nullableText('url')) as $path) {
                yield new MediaUsageReference(
                    MediaUsageReference::TYPE_MENU_ITEM,
                    $row->ulid('id'),
                    $path,
                    $row->text('label'),
                    'Меню «'.$row->text('position').'»',
                    null,
                    $row->flag('is_active') ? 'active' : 'inactive',
                );
            }
        }
    }
}
