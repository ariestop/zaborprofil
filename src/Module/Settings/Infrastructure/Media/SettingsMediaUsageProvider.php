<?php

declare(strict_types=1);

namespace App\Module\Settings\Infrastructure\Media;

use App\Module\Media\Application\Usage\MediaPathExtractor;
use App\Module\Media\Application\Usage\MediaUsageProviderInterface;
use App\Module\Media\Application\Usage\MediaUsageReference;
use App\Module\Media\Application\Usage\MediaUsageRow;
use Doctrine\DBAL\Connection;

final readonly class SettingsMediaUsageProvider implements MediaUsageProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function references(): iterable
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, scope, setting_key, CAST(setting_value AS CHAR) AS setting_value
             FROM settings WHERE CAST(setting_value AS CHAR) LIKE :like',
            ['like' => '%uploads%media%'],
        );

        foreach ($rows as $data) {
            $row = new MediaUsageRow($data);
            $key = $row->text('scope').'.'.$row->text('setting_key');
            foreach (MediaPathExtractor::extract($row->nullableText('setting_value')) as $path) {
                yield new MediaUsageReference(
                    MediaUsageReference::TYPE_SETTING,
                    $row->ulid('id'),
                    $path,
                    $key,
                    'Настройка сайта',
                    '/admin/settings',
                );
            }
        }
    }
}
