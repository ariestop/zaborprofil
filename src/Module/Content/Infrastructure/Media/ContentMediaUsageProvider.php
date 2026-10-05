<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Media;

use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Media\Application\Usage\MediaPathExtractor;
use App\Module\Media\Application\Usage\MediaUsageProviderInterface;
use App\Module\Media\Application\Usage\MediaUsageReference;
use App\Module\Media\Application\Usage\MediaUsageRow;
use Doctrine\DBAL\Connection;

final readonly class ContentMediaUsageProvider implements MediaUsageProviderInterface
{
    private const string LIKE = '%uploads%media%';

    public function __construct(private Connection $connection)
    {
    }

    public function references(): iterable
    {
        yield from $this->pageSeoReferences();
        yield from $this->blockReferences();
    }

    /**
     * @return iterable<MediaUsageReference>
     */
    private function pageSeoReferences(): iterable
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title, status, og_image, CAST(json_ld AS CHAR) AS json_ld
             FROM content_pages
             WHERE deleted_at IS NULL AND status <> :deleted
               AND (og_image LIKE :like OR CAST(json_ld AS CHAR) LIKE :like)',
            ['deleted' => PageStatus::Deleted->value, 'like' => self::LIKE],
        );

        foreach ($rows as $data) {
            $row = new MediaUsageRow($data);
            $id = $row->ulid('id');
            $fields = ['og_image' => 'OG-изображение', 'json_ld' => 'JSON-LD'];
            foreach ($fields as $column => $location) {
                foreach (MediaPathExtractor::extract($row->nullableText($column)) as $path) {
                    yield new MediaUsageReference(
                        MediaUsageReference::TYPE_PAGE_SEO,
                        $id,
                        $path,
                        $row->text('title'),
                        $location,
                        '/admin/pages/'.$id,
                        $row->text('status'),
                    );
                }
            }
        }
    }

    /**
     * @return iterable<MediaUsageReference>
     */
    private function blockReferences(): iterable
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT b.id AS block_id, b.name AS block_name, b.type AS block_type, b.page_id,
                    CAST(b.content AS CHAR) AS content, CAST(b.settings AS CHAR) AS settings,
                    p.title AS page_title, p.status AS page_status
             FROM content_page_blocks b
             INNER JOIN content_pages p ON p.id = b.page_id
             WHERE p.deleted_at IS NULL AND p.status <> :deleted
               AND (CAST(b.content AS CHAR) LIKE :like OR CAST(b.settings AS CHAR) LIKE :like)',
            ['deleted' => PageStatus::Deleted->value, 'like' => self::LIKE],
        );

        foreach ($rows as $data) {
            $row = new MediaUsageRow($data);
            $pageId = $row->ulid('page_id');
            $location = \sprintf('Блок «%s» (%s)', $row->text('block_name'), $row->text('block_type'));
            $paths = array_unique([
                ...MediaPathExtractor::extract($row->nullableText('content')),
                ...MediaPathExtractor::extract($row->nullableText('settings')),
            ]);

            foreach ($paths as $path) {
                yield new MediaUsageReference(
                    MediaUsageReference::TYPE_PAGE_BLOCK,
                    $row->ulid('block_id'),
                    $path,
                    $row->text('page_title'),
                    $location,
                    '/admin/pages/'.$pageId.'/builder',
                    $row->text('page_status'),
                );
            }
        }
    }
}
