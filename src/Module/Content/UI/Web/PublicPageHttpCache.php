<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Web;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP-кэширование публичных страниц для браузеров, CDN и nginx `fastcgi_cache`.
 *
 * Публичные заголовки выставляются только для успешных GET/HEAD-ответов анонимных посетителей.
 * Остальное (staging с Basic Auth, запрос с сессионной cookie, preview, 404, редиректы)
 * остаётся приватным и не кэшируется общими кэшами.
 *
 * ETag считается от тела ответа, поэтому учитывает и меню, и настройки, и содержимое страницы.
 * Страница с формой заявки допустима: в форме нет CSRF-токена и персональных данных,
 * а время загрузки формы и URL подставляет JavaScript.
 */
final readonly class PublicPageHttpCache
{
    public function __construct(
        private bool $enabled,
        private int $maxAge,
        private int $sharedMaxAge,
        private int $staleWhileRevalidate,
        private string $environment,
    ) {
    }

    public function apply(Response $response, Request $request): void
    {
        if (!$this->isCacheable($response, $request)) {
            $this->markPrivate($response);

            return;
        }

        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setSharedMaxAge($this->sharedMaxAge);

        if ($this->staleWhileRevalidate > 0) {
            $response->headers->addCacheControlDirective('stale-while-revalidate', (string) $this->staleWhileRevalidate);
        }

        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        // nginx читает X-Accel-Expires раньше Cache-Control и не отдаёт заголовок клиенту.
        $response->headers->set('X-Accel-Expires', (string) $this->sharedMaxAge);
        $response->isNotModified($request);
    }

    private function isCacheable(Response $response, Request $request): bool
    {
        return $this->enabled
            && $this->environment !== 'staging'
            && $response->getStatusCode() === Response::HTTP_OK
            && $request->isMethodCacheable()
            && !$request->hasPreviousSession()
            && !$request->headers->has('Authorization');
    }

    private function markPrivate(Response $response): void
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');
    }
}
