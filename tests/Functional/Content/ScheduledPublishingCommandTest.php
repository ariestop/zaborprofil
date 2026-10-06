<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\Content\Application\Service\PublicPageCacheKey;
use App\Module\Content\Application\Service\PublicPagePathNormalizer;
use App\Tests\Support\Admin\AdminApiTestCase;
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ScheduledPublishingCommandTest extends AdminApiTestCase
{
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testDuePageIsPublishedOnceAndRepeatRunIsIdempotent(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createScheduledPage($client, 'scheduled-ok', '+1 day', null);

        $client->request('GET', '/scheduled-ok/');
        self::assertResponseStatusCodeSame(404);
        $cacheKey = PublicPageCacheKey::forPath(PublicPagePathNormalizer::normalize('/scheduled-ok/'));
        self::assertTrue($this->cachePool()->getItem($cacheKey)->isHit(), 'Miss for unpublished page is cached.');

        $this->travel('+2 days');

        $tester = $this->runPublishCommand();
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('published: 1', $tester->getDisplay());
        self::assertFalse($this->cachePool()->getItem($cacheKey)->isHit(), 'Cache entry must be invalidated on scheduled publish.');

        $client->request('GET', '/scheduled-ok/');
        self::assertResponseIsSuccessful();

        $again = $this->runPublishCommand();
        self::assertSame(Command::SUCCESS, $again->getStatusCode());
        self::assertStringContainsString('Nothing to do.', $again->getDisplay());

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        $workflow = $this->json($client);
        self::assertSame('published', $workflow['status']);
        self::assertNull($workflow['scheduledPublishAt']);

        $events = array_map(fn (array $entry): string => $this->text($entry['event']), $this->rows($workflow['history']));
        self::assertSame(1, \count(array_filter($events, static fn (string $event): bool => $event === 'scheduled_published')));
    }

    public function testDryRunDoesNotChangeAnything(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createScheduledPage($client, 'scheduled-dry', '+1 day', null);
        $this->travel('+2 days');

        $tester = $this->runPublishCommand(['--dry-run' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('would_publish', $tester->getDisplay());

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        self::assertSame('scheduled', $this->json($client)['status']);
    }

    public function testPublishedPageIsUnpublishedWhenWindowEnds(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createScheduledPage($client, 'scheduled-window', '+1 day', '+3 days');

        $this->travel('+2 days');
        self::assertSame(Command::SUCCESS, $this->runPublishCommand()->getStatusCode());
        $client->request('GET', '/scheduled-window/');
        self::assertResponseIsSuccessful();

        $this->travel('+4 days');
        $tester = $this->runPublishCommand();
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('unpublished: 1', $tester->getDisplay());

        $client->request('GET', '/scheduled-window/');
        self::assertResponseStatusCodeSame(404);

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        $workflow = $this->json($client);
        self::assertSame('unpublished', $workflow['status']);
        $events = array_map(fn (array $entry): string => $this->text($entry['event']), $this->rows($workflow['history']));
        self::assertContains('scheduled_unpublished', $events);
    }

    public function testPageThatFailsChecklistIsReturnedToApprovedWithoutRetry(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createScheduledPage($client, 'scheduled-bad', '+1 day', null);
        $this->entityManager()->getConnection()->executeStatement('UPDATE content_pages SET path = :path', ['path' => '/admin/reserved/']);
        $this->entityManager()->clear();
        $this->travel('+2 days');

        $tester = $this->runPublishCommand();
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('rejected: 1', $tester->getDisplay());

        $retry = $this->runPublishCommand();
        self::assertSame(Command::SUCCESS, $retry->getStatusCode());
        self::assertStringContainsString('Nothing to do.', $retry->getDisplay());

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        $workflow = $this->json($client);
        self::assertSame('approved', $workflow['status']);
        $events = array_map(fn (array $entry): string => $this->text($entry['event']), $this->rows($workflow['history']));
        self::assertContains('schedule_failed', $events);
    }

    public function testPageWithMissedWindowIsNotPublished(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createScheduledPage($client, 'scheduled-missed', '+1 day', '+2 days');
        $this->travel('+5 days');

        $tester = $this->runPublishCommand();
        self::assertSame(Command::FAILURE, $tester->getStatusCode());

        $client->request('GET', '/scheduled-missed/');
        self::assertResponseStatusCodeSame(404);

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        self::assertSame('approved', $this->json($client)['status']);
    }

    public function testConcurrentRunIsSkippedWhileLockIsHeld(): void
    {
        $this->adminClient();
        $lockFile = \dirname(__DIR__, 3).'/var/lock/publish-scheduled.lock';
        if (!is_dir(\dirname($lockFile))) {
            mkdir(\dirname($lockFile), 0775, true);
        }
        $handle = fopen($lockFile, 'c');
        self::assertNotFalse($handle);
        self::assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        try {
            $tester = $this->runPublishCommand();
            self::assertSame(Command::SUCCESS, $tester->getStatusCode());
            self::assertStringContainsString('skipping', $tester->getDisplay());
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function testInvalidLimitIsRejected(): void
    {
        $this->adminClient();

        self::assertSame(Command::INVALID, $this->runPublishCommand(['--limit' => '0'])->getStatusCode());
        self::assertSame(Command::INVALID, $this->runPublishCommand(['--limit' => 'abc'])->getStatusCode());
    }

    private function createScheduledPage(KernelBrowser $client, string $slug, string $publishAt, ?string $unpublishAt): string
    {
        $this->api($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Страница '.$slug,
            'slug' => $slug,
            'path' => '/'.$slug.'/',
            'h1' => 'Страница '.$slug,
            'template' => 'landing',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->text($this->json($client)['id']);

        $this->api($client, 'POST', $this->url($pageId, 'blocks'), [
            'type' => 'hero',
            'name' => 'Главный экран',
            'position' => 0,
            'content' => ['title' => 'Заголовок', 'text' => 'Текст.'],
            'settings' => [],
            'isEnabled' => true,
        ]);
        self::assertResponseStatusCodeSame(201);

        $this->api($client, 'PATCH', $this->url($pageId, 'status'), ['status' => 'approved']);
        self::assertResponseIsSuccessful();

        $this->api($client, 'POST', $this->url($pageId, 'schedule'), array_filter([
            'publishAt' => (new DateTimeImmutable($publishAt))->format(DATE_ATOM),
            'unpublishAt' => $unpublishAt === null ? null : (new DateTimeImmutable($unpublishAt))->format(DATE_ATOM),
        ]));
        self::assertResponseIsSuccessful();

        return $pageId;
    }

    private function travel(string $modifier): void
    {
        Clock::set(new MockClock(new DateTimeImmutable($modifier)));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runPublishCommand(array $options = []): CommandTester
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:content:publish-scheduled'));
        $tester->execute($options);

        return $tester;
    }

    private function url(string $pageId, string $suffix): string
    {
        return '/admin/api/content/pages/'.$pageId.'/'.$suffix;
    }

    private function cachePool(): CacheItemPoolInterface
    {
        $pool = self::getContainer()->get('cache.public_page');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);

        return $pool;
    }
}
