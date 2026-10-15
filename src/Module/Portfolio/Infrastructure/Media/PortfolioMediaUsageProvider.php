<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Infrastructure\Media;

use App\Module\Media\Application\Usage\MediaPathExtractor;
use App\Module\Media\Application\Usage\MediaUsageProviderInterface;
use App\Module\Media\Application\Usage\MediaUsageReference;
use App\Module\Media\Application\Usage\MediaUsageRow;
use Doctrine\DBAL\Connection;

/**
 * Фото, видео и обложки видео в работах портфолио: медиатека не должна считать их неиспользуемыми.
 */
final readonly class PortfolioMediaUsageProvider implements MediaUsageProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function references(): iterable
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.id, p.title, p.status, m.kind, m.src, m.poster FROM portfolio_media m INNER JOIN portfolio_projects p ON p.id = m.project_id',
        );

        foreach ($rows as $data) {
            $row = new MediaUsageRow($data);
            $fields = ['src' => $row->text('kind') === 'embed' ? null : $row->nullableText('src'), 'poster' => $row->nullableText('poster')];
            foreach ($fields as $field => $value) {
                foreach (MediaPathExtractor::extract($value) as $path) {
                    yield new MediaUsageReference(
                        MediaUsageReference::TYPE_PORTFOLIO,
                        $row->ulid('id'),
                        $path,
                        $row->text('title'),
                        $field === 'poster' ? 'Портфолио: обложка видео' : 'Портфолио: фото и видео работы',
                        '/admin/portfolio/'.$row->ulid('id'),
                        $row->text('status'),
                    );
                }
            }
        }
    }
}
