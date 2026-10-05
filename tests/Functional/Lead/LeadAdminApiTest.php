<?php

declare(strict_types=1);

namespace App\Tests\Functional\Lead;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\Lead\Domain\Entity\Lead;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Admin\AdminApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class LeadAdminApiTest extends AdminApiTestCase
{
    public function testListIsPaginatedSearchableFilterableAndSortable(): void
    {
        $client = $this->adminClient();
        $this->seedLeads();

        $this->api($client, 'GET', '/admin/api/leads?perPage=2&page=1');
        self::assertResponseIsSuccessful();
        $first = $this->json($client);
        self::assertSame(5, $first['total']);
        self::assertSame(3, $first['pages']);
        self::assertSame(2, $first['perPage']);
        self::assertSame(['Анна', 'Борис'], $this->names($first));
        self::assertSame(['new', 'in_progress', 'done', 'spam'], $first['statuses']);
        self::assertSame(['calc_form', 'callback', 'public_page_form'], $first['sources']);
        self::assertSame(
            ['total' => 5, 'byStatus' => ['new' => 3, 'in_progress' => 1, 'done' => 1, 'spam' => 0]],
            $first['counts'],
        );

        $this->api($client, 'GET', '/admin/api/leads?perPage=2&page=3');
        self::assertSame(['Егор'], $this->names($this->json($client)));

        $this->api($client, 'GET', '/admin/api/leads?q=забор');
        self::assertSame(['Борис', 'Вера'], $this->names($this->json($client)));

        $this->api($client, 'GET', '/admin/api/leads?q='.rawurlencode('8 (900) 111'));
        self::assertSame(['Анна'], $this->names($this->json($client)), 'Телефон ищется по цифрам независимо от форматирования и префикса 8/+7.');

        $this->api($client, 'GET', '/admin/api/leads?q=%25');
        self::assertSame(0, $this->json($client)['total'], 'Символы LIKE экранируются.');

        $this->api($client, 'GET', '/admin/api/leads?source=callback');
        self::assertSame(['Вера', 'Егор'], $this->names($this->json($client)));

        $this->api($client, 'GET', '/admin/api/leads?status=new');
        $byStatus = $this->json($client);
        self::assertSame(3, $byStatus['total']);
        self::assertSame(['total' => 5, 'byStatus' => ['new' => 3, 'in_progress' => 1, 'done' => 1, 'spam' => 0]], $byStatus['counts'], 'Счётчики не зависят от фильтра статуса.');

        $this->api($client, 'GET', '/admin/api/leads?from=2026-09-02&to=2026-09-03');
        self::assertSame(['Борис', 'Вера'], $this->names($this->json($client)));

        $this->api($client, 'GET', '/admin/api/leads?sort=name&direction=asc&perPage=1');
        self::assertSame(['Анна'], $this->names($this->json($client)));
        $this->api($client, 'GET', '/admin/api/leads?sort=name&direction=desc&perPage=1');
        self::assertSame(['Егор'], $this->names($this->json($client)));

        $this->api($client, 'GET', '/admin/api/leads?assignee=none');
        self::assertSame(5, $this->json($client)['total']);
    }

    public function testListRejectsInvalidFiltersWithErrorCode(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'GET', '/admin/api/leads?status=archived');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->json($client)['code']);

        $this->api($client, 'GET', '/admin/api/leads?from=31.12.2026');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->json($client)['code']);
    }

    public function testListDoesNotExposePersonalDataBeyondContactFields(): void
    {
        $client = $this->adminClient();
        $this->seedLeads();

        $this->api($client, 'GET', '/admin/api/leads?perPage=1');
        $item = $this->rows($this->json($client)['items'])[0];

        self::assertArrayNotHasKey('consentSnapshot', $item);
        self::assertArrayNotHasKey('message', $item);
        self::assertArrayHasKey('messagePreview', $item);
    }

    public function testSummaryReturnsGlobalStatusCounters(): void
    {
        $client = $this->adminClient();
        $this->seedLeads();

        $this->api($client, 'GET', '/admin/api/leads/summary');
        self::assertResponseIsSuccessful();
        $summary = $this->json($client);
        self::assertSame(5, $summary['total']);
        self::assertSame(3, $summary['new']);
        self::assertSame(['new' => 3, 'in_progress' => 1, 'done' => 1, 'spam' => 0], $summary['byStatus']);
    }

    public function testDashboardReturnsDailyCountsAndWaitingLead(): void
    {
        $client = $this->adminClient();
        $this->seedLeads();
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE leads SET created_at = :now WHERE created_at >= :old',
            ['now' => (new \DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s'), 'old' => '2026-09-05 00:00:00'],
        );

        $this->api($client, 'GET', '/admin/api/leads/dashboard');
        self::assertResponseIsSuccessful();
        $dashboard = $this->json($client);
        self::assertIsArray($dashboard['daily']);
        self::assertCount(14, $dashboard['daily']);
        $perDay = 0;
        foreach ($dashboard['daily'] as $day) {
            self::assertIsArray($day);
            self::assertIsInt($day['count']);
            $perDay += $day['count'];
        }
        self::assertSame($perDay, $dashboard['createdLast14Days']);
        self::assertGreaterThan(0, $perDay);
        self::assertIsInt($dashboard['doneLastWeek']);
        self::assertIsString($dashboard['oldestNewAt']);
    }

    public function testCardShowsDetailsAndWritesViewToAudit(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads()['Борис'];

        $this->api($client, 'GET', '/admin/api/leads/'.$lead->id());
        self::assertResponseIsSuccessful();
        $card = $this->json($client);
        self::assertSame('Борис', $card['name']);
        self::assertSame('Нужен забор из профнастила', $card['message']);
        self::assertIsArray($card['consentSnapshot']);
        self::assertSame([], $card['events']);
        self::assertNull($card['assignee']);

        $audit = $this->auditEntries('lead.viewed');
        self::assertCount(1, $audit);
        self::assertSame((string) $lead->id(), $audit[0]->entityId());
        self::assertStringNotContainsString('900', json_encode($audit[0]->newValues(), JSON_THROW_ON_ERROR));
    }

    public function testUnknownLeadReturnsNotFoundCode(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'GET', '/admin/api/leads/01ARZ3NDEKTSV4RRFFQ69G5FAV');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Lead not found.', 'code' => 'NOT_FOUND'], $this->json($client));
    }

    public function testStatusChangeIsRecordedInHistoryAndAudit(): void
    {
        $client = $this->adminClient('manager@example.test');
        $lead = $this->seedLeads()['Анна'];

        $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/status', ['status' => 'in_progress']);
        self::assertResponseIsSuccessful();
        $card = $this->json($client);
        self::assertSame('in_progress', $card['status']);
        $events = $this->rows($card['events']);
        self::assertCount(1, $events);
        self::assertSame('status_changed', $events[0]['type']);
        self::assertSame(['from' => 'new', 'to' => 'in_progress'], $events[0]['data']);
        self::assertSame('manager@example.test', $events[0]['actorLabel']);

        $audit = $this->auditEntries('lead.status_changed');
        self::assertCount(1, $audit);
        self::assertSame(['status' => 'new'], $audit[0]->oldValues());
        self::assertSame(['status' => 'in_progress'], $audit[0]->newValues());

        $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/status', ['status' => 'in_progress']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->rows($this->json($client)['events']), 'Повторная установка того же статуса не создаёт событий.');
        self::assertCount(1, $this->auditEntries('lead.status_changed'));

        $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/status', ['status' => 'closed']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'Lead status is not allowed.', 'code' => 'VALIDATION'], $this->json($client));

        $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/status', []);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->json($client)['code']);
    }

    public function testNotesAreAddedToHistoryWithoutLeakingTextToAudit(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads()['Анна'];

        $this->api($client, 'POST', '/admin/api/leads/'.$lead->id().'/notes', ['text' => '  Перезвонить после 18:00  ']);
        self::assertResponseStatusCodeSame(201);
        $events = $this->rows($this->json($client)['events']);
        self::assertSame('note', $events[0]['type']);
        self::assertSame('Перезвонить после 18:00', $events[0]['body']);

        $audit = $this->auditEntries('lead.note_added');
        self::assertCount(1, $audit);
        self::assertSame(['length' => mb_strlen('Перезвонить после 18:00')], $audit[0]->newValues());

        $this->api($client, 'POST', '/admin/api/leads/'.$lead->id().'/notes', ['text' => '   ']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->json($client)['code']);

        $this->api($client, 'POST', '/admin/api/leads/'.$lead->id().'/notes', ['text' => str_repeat('я', 2001)]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAssigneeCanBeSetFilteredAndCleared(): void
    {
        $client = $this->adminClient('admin@example.test');
        $lead = $this->seedLeads()['Анна'];
        $manager = new AdminUser('manager@example.test', 'hash', ['ROLE_MANAGER']);
        $editor = new AdminUser('editor@example.test', 'hash', ['ROLE_EDITOR']);
        $inactive = new AdminUser('inactive@example.test', 'hash', ['ROLE_MANAGER']);
        $inactive->deactivate();
        foreach ([$manager, $editor, $inactive] as $user) {
            $this->entityManager()->persist($user);
        }
        $this->entityManager()->flush();

        $this->api($client, 'GET', '/admin/api/leads/assignees');
        self::assertResponseIsSuccessful();
        $emails = array_column($this->rows($this->json($client)['items']), 'email');
        self::assertContains('manager@example.test', $emails);
        self::assertContains('admin@example.test', $emails);
        self::assertNotContains('editor@example.test', $emails);
        self::assertNotContains('inactive@example.test', $emails);

        $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/assignee', ['assigneeId' => (string) $manager->id()]);
        self::assertResponseIsSuccessful();
        $card = $this->json($client);
        self::assertSame(['id' => (string) $manager->id(), 'email' => 'manager@example.test'], $card['assignee']);
        $events = $this->rows($card['events']);
        self::assertSame('assigned', $events[0]['type']);
        self::assertIsArray($events[0]['data']);
        self::assertSame('manager@example.test', $events[0]['data']['toLabel'] ?? null);
        self::assertCount(1, $this->auditEntries('lead.assigned'));

        $this->api($client, 'GET', '/admin/api/leads?assignee='.(string) $manager->id());
        self::assertSame(['Анна'], $this->names($this->json($client)));
        $this->api($client, 'GET', '/admin/api/leads?assignee=none');
        self::assertSame(4, $this->json($client)['total']);
        $this->api($client, 'GET', '/admin/api/leads?assignee=me');
        self::assertSame(0, $this->json($client)['total']);

        foreach ([$editor, $inactive] as $invalid) {
            $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/assignee', ['assigneeId' => (string) $invalid->id()]);
            self::assertResponseStatusCodeSame(422);
            self::assertSame('VALIDATION', $this->json($client)['code']);
        }

        $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/assignee', ['assigneeId' => null]);
        self::assertResponseIsSuccessful();
        self::assertNull($this->json($client)['assignee']);
    }

    public function testExportProducesEscapedCsvForFilteredLeadsAndIsAudited(): void
    {
        $client = $this->adminClient();
        $this->seedLeads();
        $malicious = new Lead('callback', '=HYPERLINK("http://evil.test","x")', '+79005550000', '@cmd@example.test', "-2+3\nвторая строка", ['consent' => true, 'ip' => '10.0.0.1']);
        $this->entityManager()->persist($malicious);
        $this->entityManager()->flush();

        $this->api($client, 'GET', '/admin/api/leads/export?source=callback&status=new');
        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename="leads-', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $csv = (string) $response->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBF".'id,created_at,name,phone,email,source,status,assignee,spam_score,message', $csv);
        self::assertStringContainsString("'=HYPERLINK", $csv);
        self::assertStringContainsString("'@cmd@example.test", $csv);
        self::assertStringContainsString("\"'-2+3\nвторая строка\"", $csv);
        self::assertStringContainsString('+79005550000', $csv);
        self::assertStringNotContainsString("\n=HYPERLINK", $csv);
        self::assertStringNotContainsString('10.0.0.1', $csv, 'IP и снимок согласия не выгружаются.');
        self::assertStringNotContainsString('Вера', $csv, 'Фильтры применяются и к экспорту.');
        self::assertStringContainsString('Егор', $csv);

        $audit = $this->auditEntries('lead.exported');
        self::assertCount(1, $audit);
        $values = $audit[0]->newValues();
        self::assertSame(2, $values['count']);
        self::assertEquals(['status' => 'new', 'source' => 'callback', 'from' => null, 'to' => null, 'assignee' => null, 'hasQuery' => false], $values['filters']);
    }

    public function testManagerCanCreateLeadAfterCallAndMarkLeadsRead(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads()['Анна'];

        $this->api($client, 'POST', '/admin/api/leads', ['name' => 'ООО «СтройДвор»', 'phone' => '+7 900 000-80-02', 'email' => '', 'message' => 'Нужна смета на 2000 штакетин']);
        self::assertResponseStatusCodeSame(201);
        $created = $this->json($client);
        self::assertSame('phone_call', $created['source']);
        self::assertSame('new', $created['status']);
        self::assertTrue($created['b2b']);
        self::assertNotNull($created['readAt']);
        self::assertIsArray($created['consentSnapshot']);
        self::assertFalse($created['consentSnapshot']['consent'] ?? null);
        self::assertCount(1, $this->auditEntries('lead.created_manually'));

        $this->api($client, 'POST', '/admin/api/leads', ['name' => 'Иван', 'phone' => '12']);
        self::assertResponseStatusCodeSame(422);

        $this->api($client, 'GET', '/admin/api/leads?status=active&perPage=100');
        self::assertNotContains('spam', array_column($this->rows($this->json($client)['items']), 'status'));
        self::assertSame(6, $this->json($client)['total']);

        $this->api($client, 'GET', '/admin/api/leads?b2b=1');
        self::assertSame(['ООО «СтройДвор»'], $this->names($this->json($client)));

        $this->api($client, 'GET', '/admin/api/leads/'.$lead->id());
        self::assertNull($this->json($client)['readAt']);
        $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/read');
        self::assertResponseIsSuccessful();
        self::assertNotNull($this->json($client)['readAt']);

        $this->api($client, 'GET', '/admin/api/leads?waiting=2');
        $waiting = $this->names($this->json($client));
        self::assertContains('Анна', $waiting);
        self::assertNotContains('ООО «СтройДвор»', $waiting, 'Свежая заявка ещё не считается неотвеченной.');
        self::assertNotContains('Вера', $waiting, 'Заявка в работе не считается неотвеченной.');
    }

    public function testMutationsRequireManagePermissionAndExportRequiresExportPermission(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads()['Анна'];
        $this->denyAllPermissions($client);

        $requests = [
            ['GET', '/admin/api/leads'],
            ['GET', '/admin/api/leads/summary'],
            ['GET', '/admin/api/leads/dashboard'],
            ['GET', '/admin/api/leads/assignees'],
            ['GET', '/admin/api/leads/export'],
            ['GET', '/admin/api/leads/'.$lead->id()],
            ['PATCH', '/admin/api/leads/'.$lead->id().'/status'],
            ['PATCH', '/admin/api/leads/'.$lead->id().'/assignee'],
            ['POST', '/admin/api/leads/'.$lead->id().'/notes'],
            ['POST', '/admin/api/leads'],
            ['PATCH', '/admin/api/leads/'.$lead->id().'/read'],
        ];
        foreach ($requests as [$method, $uri]) {
            $this->api($client, $method, $uri, $method === 'GET' ? [] : ['status' => 'done', 'text' => 'x', 'assigneeId' => null]);
            self::assertResponseStatusCodeSame(403, $method.' '.$uri);
            self::assertSame(['error' => 'Access denied.', 'code' => 'ACCESS_DENIED'], $this->json($client));
        }

        self::assertSame('new', $this->entityManager()->getRepository(Lead::class)->find($lead->id())?->status());
        self::assertSame([], $this->auditEntries('lead.exported'));
    }

    private function denyAllPermissions(KernelBrowser $client): void
    {
        $client->disableReboot();
        self::getContainer()->set('security.authorization_checker', new class () implements AuthorizationCheckerInterface {
            public function isGranted(mixed $attribute, mixed $subject = null, mixed $vote = null): bool
            {
                return false;
            }
        });
    }

    /**
     * @return array<string, Lead>
     */
    private function seedLeads(): array
    {
        $rows = [
            ['Анна', 'public_page_form', '+7 (900) 111-22-33', 'Нужны ворота', 'new', '2026-09-05 10:00:00'],
            ['Борис', 'calc_form', '+7 900 222-33-44', 'Нужен забор из профнастила', 'new', '2026-09-03 12:00:00'],
            ['Вера', 'callback', '+7 900 333-44-55', 'Хочу забор и калитку', 'in_progress', '2026-09-02 09:00:00'],
            ['Георгий', 'public_page_form', '+7 900 444-55-66', null, 'done', '2026-09-01 09:00:00'],
            ['Егор', 'callback', '+7 900 555-66-77', 'Просто интересуюсь', 'new', '2026-08-30 09:00:00'],
        ];
        $entityManager = $this->entityManager();
        $leads = [];
        foreach ($rows as [$name, $source, $phone, $message, $status, $createdAt]) {
            $lead = new Lead($source, $name, $phone, null, $message, ['consent' => true, 'ip' => '203.0.113.10']);
            if ($status !== 'new') {
                $lead->updateStatus($status);
            }
            $entityManager->persist($lead);
            $entityManager->flush();
            $entityManager->getConnection()->executeStatement(
                'UPDATE leads SET created_at = :createdAt WHERE id = :id',
                ['createdAt' => $createdAt, 'id' => $lead->id()->toBinary()],
            );
            $leads[$name] = $lead;
        }
        $entityManager->clear();

        return $leads;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return list<string>
     */
    private function names(array $response): array
    {
        return array_map(fn (array $row): string => $this->text($row['name']), $this->rows($response['items']));
    }

    /**
     * @return list<AuditLogEntry>
     */
    private function auditEntries(string $action): array
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();

        return $entityManager->getRepository(AuditLogEntry::class)->findBy(['action' => $action]);
    }
}
