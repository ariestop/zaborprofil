<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Usage;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Источник ссылок на файлы медиатеки. Каждый модуль, который хранит пути `/uploads/media/...`
 * (страницы, каталог, меню, настройки), реализует интерфейс и отдаёт найденные ссылки.
 */
#[AutoconfigureTag(self::TAG)]
interface MediaUsageProviderInterface
{
    public const string TAG = 'app.media.usage_provider';

    /**
     * @return iterable<MediaUsageReference>
     */
    public function references(): iterable;
}
