<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20261004000200 extends AbstractMigration
{
    private const string HOME_PAGE_ULID = '01JV6Q5X5H3Q8Q1F6H2T4M7N8P';
    private const string SLIDER_BLOCK_ULID = '01JV6Q5X5H3Q8Q1F6H2T4M7N8Q';

    public function getDescription(): string
    {
        return 'Seed default homepage (/) with slider block for local/dev bootstrap.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $pageId = bin2hex(Ulid::fromString(self::HOME_PAGE_ULID)->toBinary());
        $sliderBlockId = bin2hex(Ulid::fromString(self::SLIDER_BLOCK_ULID)->toBinary());
        $now = '2026-05-11 21:15:00';
        $sliderContent = json_encode([
            'items' => [
                [
                    'src' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1600&q=80',
                    'alt' => 'Монтаж забора на участке',
                    'title' => 'Заборы под ключ в Москве и области',
                    'text' => 'Производим, доставляем и устанавливаем ограждения с гарантией и фиксированными сроками.',
                    'buttonLabel' => 'Рассчитать стоимость',
                    'buttonHref' => '#lead-form',
                ],
                [
                    'src' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1600&q=80',
                    'alt' => 'Готовый объект с современным забором',
                    'title' => 'Прозрачная смета без скрытых доплат',
                    'text' => 'Подбираем материал под бюджет и условия участка, показываем итоговую стоимость до старта работ.',
                    'buttonLabel' => 'Смотреть примеры',
                    'buttonHref' => '/portfolio/',
                ],
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        $sliderSettings = json_encode([
            'className' => '',
            'autoplay' => true,
            'loop' => true,
            'pagination' => true,
            'navigation' => true,
            'delayMs' => 4500,
        ], \JSON_THROW_ON_ERROR);

        $this->addSql(
            'INSERT INTO content_pages (id, parent_id, type, title, slug, path, h1, status, template, sort_order, indexable, visibility, published_at, scheduled_publish_at, scheduled_unpublish_at, created_by, updated_by, published_by, created_at, updated_at, deleted_at)
             SELECT UNHEX(?), NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, ?, ?, NULL
             FROM DUAL
             WHERE NOT EXISTS (
                SELECT 1 FROM content_pages existing
                WHERE existing.path = ? AND existing.deleted_at IS NULL
             )',
            [
                $pageId,
                'home',
                'Главная',
                'home',
                '/',
                'Заборы под ключ',
                'published',
                'default',
                0,
                1,
                'public',
                $now,
                $now,
                $now,
                '/',
            ],
        );

        $this->addSql(
            'INSERT INTO content_page_blocks (id, page_id, type, name, position, is_enabled, visibility, content, settings, created_at, updated_at)
             SELECT UNHEX(?), page.id, ?, ?, ?, ?, ?, ?, ?, ?, ?
             FROM content_pages page
             WHERE page.id = UNHEX(?)
               AND NOT EXISTS (
                    SELECT 1
                    FROM content_page_blocks existing
                    WHERE existing.page_id = page.id
                      AND existing.type = ?
               )',
            [
                $sliderBlockId,
                'slider',
                'Главный слайдер',
                0,
                1,
                'public',
                $sliderContent,
                $sliderSettings,
                $now,
                $now,
                $pageId,
                'slider',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        $pageId = bin2hex(Ulid::fromString(self::HOME_PAGE_ULID)->toBinary());

        $this->addSql('DELETE FROM content_page_blocks WHERE page_id = UNHEX(?)', [$pageId]);
        $this->addSql('DELETE FROM content_pages WHERE id = UNHEX(?)', [$pageId]);
    }
}
