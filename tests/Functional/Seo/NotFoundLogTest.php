<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use App\Module\Seo\Application\NotFound\NotFoundRecorder;
use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\NotFoundLogRepositoryInterface;
use App\Module\Seo\Domain\Repository\NotFoundSearchCriteria;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use App\Tests\Support\Admin\AdminApiTestCase;
use App\Tests\Support\Database\SchemaTestHelper;
use DateTimeImmutable;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class NotFoundLogTest extends AdminApiTestCase
{
    public function testPublicNotFoundIsAggregatedPerPath(): void
    {
        $client = $this->adminClient();

        $client->request('GET', '/missing-page/');
        $client->request('GET', '/missing-page/?utm=1', server: ['HTTP_REFERER' => 'https://www.google.com/search?q=secret&token=1']);
        $client->request('GET', '/other-missing');
        $client->request('GET', '/admin/api/seo/unknown-endpoint');

        $result = $this->log()->search(new NotFoundSearchCriteria());
        self::assertSame(2, $result->total);
        self::assertSame(3, $result->totalHits);
        self::assertSame('/missing-page/', $result->items[0]->path());
        self::assertSame(2, $result->items[0]->hitCount());
        self::assertSame('https://www.google.com/search', $result->items[0]->referrer());
    }

    public function testCyrillicPathIsStoredPercentEncoded(): void
    {
        $client = $this->adminClient();

        $client->request('GET', '/старая-страница/');

        $items = $this->log()->search(new NotFoundSearchCriteria())->items;
        self::assertCount(1, $items);
        self::assertSame('/%D1%81%D1%82%D0%B0%D1%80%D0%B0%D1%8F-%D1%81%D1%82%D1%80%D0%B0%D0%BD%D0%B8%D1%86%D0%B0/', $items[0]->path());
    }

    public function testExistingPagesAndRedirectsAreNotLogged(): void
    {
        $client = $this->adminClient();
        $this->redirects()->save(new Redirect('/moved/', '/target/'));

        $client->request('GET', '/moved/');
        $client->request('GET', '/health');
        $client->request('POST', '/nothing-here/');

        self::assertSame(0, $this->log()->search(new NotFoundSearchCriteria())->total);
    }

    public function testNewPathsAreIgnoredWhenLimitIsReachedButExistingStillCount(): void
    {
        $this->adminClient();
        $log = $this->log();
        $now = new DateTimeImmutable();

        $log->registerHit('/a/', null, $now, 2);
        $log->registerHit('/b/', null, $now, 2);
        $log->registerHit('/c/', null, $now, 2);
        $log->registerHit('/a/', null, $now, 2);

        $result = $log->search(new NotFoundSearchCriteria());
        self::assertSame(2, $result->total);
        self::assertSame('/a/', $result->items[0]->path());
        self::assertSame(2, $result->items[0]->hitCount());
    }

    public function testRecorderSkipsOverlongPaths(): void
    {
        $this->adminClient();
        $recorder = self::getContainer()->get(NotFoundRecorder::class);
        self::assertInstanceOf(NotFoundRecorder::class, $recorder);

        $recorder->record('/'.str_repeat('a', NotFoundRecorder::MAX_PATH_LENGTH), null);

        self::assertSame(0, $this->log()->search(new NotFoundSearchCriteria())->total);
    }

    public function testAdminApiListsSearchesAndMarksCoveredPaths(): void
    {
        $client = $this->adminClient();
        $now = new DateTimeImmutable();
        $this->log()->registerHit('/old-fence/', 'https://example.com/ref', $now, 100);
        $this->log()->registerHit('/old-fence/', null, $now, 100);
        $this->log()->registerHit('/old-gate/', null, $now, 100);
        $this->redirects()->save(new Redirect('/old-gate/', '/gate/'));

        $this->api($client, 'GET', '/admin/api/seo/not-found');
        self::assertResponseIsSuccessful();
        $payload = $this->json($client);
        self::assertSame(2, $payload['total']);
        self::assertSame(3, $payload['totalHits']);
        $items = $this->rows($payload['items']);
        self::assertSame('/old-fence/', $items[0]['path']);
        self::assertSame(2, $items[0]['hitCount']);
        self::assertSame('https://example.com/ref', $items[0]['referrer']);
        self::assertFalse($items[0]['hasRedirect']);
        self::assertTrue($items[1]['hasRedirect']);

        $this->api($client, 'GET', '/admin/api/seo/not-found?q=gate');
        self::assertSame(1, $this->json($client)['total']);
    }

    public function testAdminCanDeleteEntryAndClearJournal(): void
    {
        $client = $this->adminClient();
        $now = new DateTimeImmutable();
        $this->log()->registerHit('/one/', null, $now, 100);
        $this->log()->registerHit('/two/', null, $now, 100);
        $this->log()->registerHit('/three/', null, $now->modify('-100 days'), 100);

        $this->api($client, 'DELETE', '/admin/api/seo/not-found?olderThanDays=30');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json($client)['removed']);

        $this->api($client, 'DELETE', '/admin/api/seo/not-found?olderThanDays=0');
        self::assertResponseStatusCodeSame(422);

        $first = $this->log()->search(new NotFoundSearchCriteria())->items[0];
        $this->api($client, 'DELETE', '/admin/api/seo/not-found/'.$first->id());
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->log()->search(new NotFoundSearchCriteria())->total);

        $this->api($client, 'DELETE', '/admin/api/seo/not-found/'.$first->id());
        self::assertResponseStatusCodeSame(404);
        self::assertSame('NOT_FOUND', $this->json($client)['code']);

        $this->api($client, 'DELETE', '/admin/api/seo/not-found');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json($client)['removed']);
        self::assertSame(0, $this->log()->search(new NotFoundSearchCriteria())->total);
    }

    public function testPruneCommandRemovesStaleEntries(): void
    {
        SchemaTestHelper::recreateSchema($this->entityManager());
        $now = new DateTimeImmutable();
        $this->log()->registerHit('/fresh/', null, $now, 100);
        $this->log()->registerHit('/stale/', null, $now->modify('-120 days'), 100);

        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:seo:not-found:prune'));
        $tester->execute(['--days' => '90']);

        $tester->assertCommandIsSuccessful();
        $items = $this->log()->search(new NotFoundSearchCriteria())->items;
        self::assertCount(1, $items);
        self::assertSame('/fresh/', $items[0]->path());

        $tester->execute(['--days' => 'abc']);
        self::assertNotSame(0, $tester->getStatusCode());
    }

    private function log(): NotFoundLogRepositoryInterface
    {
        $repository = self::getContainer()->get(NotFoundLogRepositoryInterface::class);
        if (!$repository instanceof NotFoundLogRepositoryInterface) {
            throw new LogicException('404 log repository is not available.');
        }

        return $repository;
    }

    private function redirects(): RedirectRepositoryInterface
    {
        $repository = self::getContainer()->get(RedirectRepositoryInterface::class);
        if (!$repository instanceof RedirectRepositoryInterface) {
            throw new LogicException('Redirect repository is not available.');
        }

        return $repository;
    }
}
