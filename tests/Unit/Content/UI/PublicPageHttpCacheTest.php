<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\UI;

use App\Module\Content\UI\Web\PublicPageHttpCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class PublicPageHttpCacheTest extends TestCase
{
    public function testAnonymousGetBecomesPublicWithEtag(): void
    {
        $response = new Response('<html>page</html>');

        $this->cache()->apply($response, Request::create('/zabory/'));

        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('max-age=0', $cacheControl);
        self::assertStringContainsString('s-maxage=300', $cacheControl);
        self::assertStringContainsString('stale-while-revalidate=60', $cacheControl);
        self::assertSame('"'.hash('xxh128', '<html>page</html>').'"', $response->headers->get('ETag'));
        self::assertSame('300', $response->headers->get('X-Accel-Expires'));
    }

    public function testMatchingEtagTurnsResponseIntoNotModified(): void
    {
        $etag = '"'.hash('xxh128', '<html>page</html>').'"';
        $request = Request::create('/zabory/', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        $response = new Response('<html>page</html>');

        $this->cache()->apply($response, $request);

        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame($etag, $response->headers->get('ETag'));
    }

    public function testChangedContentChangesEtag(): void
    {
        $first = new Response('<html>one</html>');
        $second = new Response('<html>two</html>');

        $this->cache()->apply($first, Request::create('/'));
        $this->cache()->apply($second, Request::create('/'));

        self::assertNotSame($first->headers->get('ETag'), $second->headers->get('ETag'));
    }

    public function testDisabledCacheKeepsResponsePrivate(): void
    {
        $response = new Response('page');

        $this->cache(enabled: false)->apply($response, Request::create('/'));

        $this->assertNotShared($response);
    }

    public function testStagingIsNeverCachedPublicly(): void
    {
        $response = new Response('page');

        $this->cache(environment: 'staging')->apply($response, Request::create('/'));

        $this->assertNotShared($response);
    }

    public function testRequestWithSessionCookieStaysPrivate(): void
    {
        $request = Request::create('/', cookies: ['MOCKSESSID' => 'abc']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = new Response('page');

        $this->cache()->apply($response, $request);

        $this->assertNotShared($response);
    }

    public function testAuthorizedRequestStaysPrivate(): void
    {
        $response = new Response('page');

        $this->cache()->apply($response, Request::create('/', server: ['HTTP_AUTHORIZATION' => 'Basic Zm9vOmJhcg==']));

        $this->assertNotShared($response);
    }

    public function testNonCacheableMethodStaysPrivate(): void
    {
        $response = new Response('page');

        $this->cache()->apply($response, Request::create('/', 'POST'));

        $this->assertNotShared($response);
    }

    public function testErrorResponsesAreNotCachedPublicly(): void
    {
        $response = new Response('missing', Response::HTTP_NOT_FOUND);

        $this->cache()->apply($response, Request::create('/missing/'));

        $this->assertNotShared($response);
    }

    private function assertNotShared(Response $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-cache', $cacheControl);
        self::assertStringNotContainsString('public', $cacheControl);
        self::assertFalse($response->headers->has('ETag'));
        self::assertFalse($response->headers->has('X-Accel-Expires'));
    }

    private function cache(bool $enabled = true, string $environment = 'prod'): PublicPageHttpCache
    {
        return new PublicPageHttpCache($enabled, 0, 300, 60, $environment);
    }
}
