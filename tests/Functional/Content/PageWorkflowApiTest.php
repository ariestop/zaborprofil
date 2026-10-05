<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Admin\AdminApiTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class PageWorkflowApiTest extends AdminApiTestCase
{
    public function testPageGoesThroughReviewApprovalAndPublishWithJournalHistory(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'workflow-approve');

        $this->api($client, 'PATCH', $this->url($pageId, 'status'), ['status' => 'review', 'comment' => 'На проверку']);
        self::assertResponseIsSuccessful();
        self::assertSame('review', $this->json($client)['status']);

        $this->api($client, 'PATCH', $this->url($pageId, 'status'), ['status' => 'approved', 'comment' => 'Проверено SEO']);
        self::assertResponseIsSuccessful();
        self::assertSame('approved', $this->json($client)['status']);

        $this->api($client, 'PATCH', $this->url($pageId, 'status'), ['status' => 'review']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->json($client)['code']);

        $this->api($client, 'POST', $this->url($pageId, 'publish'), ['comment' => 'Выпуск']);
        self::assertResponseIsSuccessful();
        self::assertSame('published', $this->json($client)['status']);

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        self::assertResponseIsSuccessful();
        $workflow = $this->json($client);

        self::assertSame('published', $workflow['status']);
        self::assertFalse($workflow['hasUnpublishedChanges']);
        self::assertIsArray($workflow['publishedRevision']);

        $history = $this->rows($workflow['history']);
        $events = array_map(fn (array $entry): string => $this->text($entry['event']), $history);
        self::assertContains('published', $events);
        self::assertSame(2, \count(array_filter($events, static fn (string $event): bool => $event === 'status_changed')));
    }

    public function testPagesListShowsWhoChangedThePageLast(): void
    {
        $client = $this->adminClient('editor@example.test');
        $editor = $this->entityManager()->getRepository(AdminUser::class)->findOneBy(['email' => 'editor@example.test']);
        self::assertInstanceOf(AdminUser::class, $editor);
        $editor->rename('Игорь');
        $this->entityManager()->flush();

        $pageId = $this->createPage($client, 'who-changed');

        $listed = $this->listedPage($client, $pageId);
        self::assertSame((string) $editor->id(), $listed['updatedBy']);
        self::assertSame('Игорь', $listed['updatedByName']);

        $this->api($client, 'PATCH', $this->url($pageId, 'status'), ['status' => 'review']);
        self::assertResponseIsSuccessful();
        self::assertSame('Игорь', $this->listedPage($client, $pageId)['updatedByName']);
    }

    public function testStatusPatchPublishesPageAndWorkflowListsTransitions(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'workflow-patch-publish');

        $this->api($client, 'PATCH', $this->url($pageId, 'status'), ['status' => 'published']);
        self::assertResponseIsSuccessful();
        self::assertSame('published', $this->json($client)['status']);

        $client->request('GET', '/workflow-patch-publish/');
        self::assertResponseIsSuccessful();

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        $transitions = $this->rows($this->json($client)['transitions']);
        self::assertContains('unpublished', array_map(fn (array $row): string => $this->text($row['status']), $transitions));
    }

    public function testScheduleValidationReturnsStructuredErrors(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'workflow-schedule-invalid');

        $this->api($client, 'POST', $this->url($pageId, 'schedule'), ['publishAt' => $this->future('+1 day')]);
        self::assertResponseStatusCodeSame(422);
        $payload = $this->json($client);
        self::assertArrayHasKey('error', $payload);
        self::assertArrayHasKey('code', $payload);

        $this->api($client, 'PATCH', $this->url($pageId, 'status'), ['status' => 'approved']);
        self::assertResponseIsSuccessful();

        $this->api($client, 'POST', $this->url($pageId, 'schedule'), ['publishAt' => (new DateTimeImmutable('-1 hour'))->format(DATE_ATOM)]);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('code', $this->json($client));

        $this->api($client, 'POST', $this->url($pageId, 'schedule'), ['publishAt' => 'не дата']);
        self::assertResponseStatusCodeSame(422);

        $this->api($client, 'POST', $this->url($pageId, 'schedule'), [
            'publishAt' => $this->future('+2 days'),
            'unpublishAt' => $this->future('+1 day'),
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testScheduleAndCancelScheduleRoundTrip(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'workflow-schedule');

        $this->api($client, 'PATCH', $this->url($pageId, 'status'), ['status' => 'approved']);
        self::assertResponseIsSuccessful();

        $publishAt = $this->future('+1 day');
        $this->api($client, 'POST', $this->url($pageId, 'schedule'), [
            'publishAt' => $publishAt,
            'unpublishAt' => $this->future('+3 days'),
            'comment' => 'Акция',
        ]);
        self::assertResponseIsSuccessful();
        $scheduled = $this->json($client);
        self::assertSame('scheduled', $scheduled['status']);
        self::assertNotNull($scheduled['scheduledPublishAt']);

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        $workflow = $this->json($client);
        self::assertTrue($workflow['canCancelSchedule']);
        self::assertIsArray($workflow['scheduledRevision']);

        $client->request('GET', '/workflow-schedule/');
        self::assertResponseStatusCodeSame(404);

        $this->api($client, 'DELETE', $this->url($pageId, 'schedule'), ['comment' => 'Отмена']);
        self::assertResponseIsSuccessful();
        $cancelled = $this->json($client);
        self::assertSame('approved', $cancelled['status']);
        self::assertNull($cancelled['scheduledPublishAt']);

        $this->api($client, 'DELETE', $this->url($pageId, 'schedule'));
        self::assertResponseStatusCodeSame(422);

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        $events = array_map(
            fn (array $entry): string => $this->text($entry['event']),
            $this->rows($this->json($client)['history']),
        );
        self::assertContains('scheduled', $events);
        self::assertContains('schedule_cancelled', $events);
    }

    public function testRevisionDiffAndRollbackWriteJournal(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'workflow-diff');

        $this->api($client, 'POST', $this->url($pageId, 'publish'));
        self::assertResponseIsSuccessful();

        $this->api($client, 'GET', $this->url($pageId, 'revisions'));
        $firstRevisionId = $this->text($this->rows($this->json($client)['revisions'])[0]['id']);

        $this->api($client, 'PUT', '/admin/api/content/pages/'.$pageId, [
            'type' => 'landing',
            'title' => 'Новый заголовок',
            'slug' => 'workflow-diff',
            'path' => '/workflow-diff/',
            'h1' => 'Новый заголовок',
            'template' => 'landing',
        ]);
        self::assertResponseIsSuccessful();

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        self::assertTrue($this->json($client)['hasUnpublishedChanges']);

        $listed = $this->listedPage($client, $pageId);
        self::assertTrue($listed['hasUnpublishedChanges']);
        self::assertSame('admin-api@example.test', $listed['updatedByName']);
        self::assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $this->text($listed['updatedAt'])));

        $this->api($client, 'GET', $this->url($pageId, 'revisions/diff').'?from='.$firstRevisionId);
        self::assertResponseIsSuccessful();
        $diff = $this->json($client);
        self::assertTrue($diff['hasChanges']);
        self::assertIsArray($diff['fields']);

        $this->api($client, 'GET', $this->url($pageId, 'revisions/diff'));
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('code', $this->json($client));

        $this->api($client, 'POST', $this->url($pageId, 'revisions/'.$firstRevisionId.'/rollback'));
        self::assertResponseIsSuccessful();

        $this->api($client, 'GET', $this->url($pageId, 'workflow'));
        $events = array_map(
            fn (array $entry): string => $this->text($entry['event']),
            $this->rows($this->json($client)['history']),
        );
        self::assertContains('rolled_back', $events);
    }

    private function createPage(KernelBrowser $client, string $slug): string
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

        return $pageId;
    }

    /**
     * @return array<string, mixed>
     */
    private function listedPage(KernelBrowser $client, string $pageId): array
    {
        $this->api($client, 'GET', '/admin/api/content/pages');
        self::assertResponseIsSuccessful();

        foreach ($this->rows($this->json($client)['pages']) as $page) {
            if (($page['id'] ?? null) === $pageId) {
                return $page;
            }
        }

        self::fail('Page is missing in the admin list.');
    }

    private function url(string $pageId, string $suffix): string
    {
        return '/admin/api/content/pages/'.$pageId.'/'.$suffix;
    }

    private function future(string $modifier): string
    {
        return (new DateTimeImmutable($modifier))->format(DATE_ATOM);
    }
}
