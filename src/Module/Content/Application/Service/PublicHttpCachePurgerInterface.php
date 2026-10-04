<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

/**
 * Сбрасывает HTTP-кэш публичных страниц на стороне веб-сервера (nginx fastcgi_cache).
 *
 * Реализации не должны бросать исключения: сбой очистки внешнего кэша не может ломать
 * публикацию страницы, а устаревшая копия ограничена s-maxage.
 */
interface PublicHttpCachePurgerInterface
{
    public function purgePath(string $path): void;

    public function purgeAll(): void;
}
