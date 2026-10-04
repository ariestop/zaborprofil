<?php

declare(strict_types=1);

namespace App\Tests\Functional\Lead;

use App\Module\Lead\Application\Workflow\LeadWorkflow;
use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use App\Tests\Support\Admin\AdminApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Пограничные случаи Admin API заявок: пагинация, фильтры, переходы статусов, заметки, назначение.
 */
final class LeadAdminEdgeCasesTest extends AdminApiTestCase
{
    public function testPaginationParametersAreClampedAndNonNumericIsBadRequest(): void
    {
        $client = $this->adminClient();
        $this->seedLeads(3);

        $this->api($client, 'GET', '/admin/api/leads?perPage=1000');
        self::assertResponseIsSuccessful();
        self::assertSame(100, $this->json($client)['perPage']);

        $this->api($client, 'GET', '/admin/api/leads?perPage=0&page=-5');
        self::assertResponseIsSuccessful();
        $clamped = $this->json($client);
        self::assertSame(1, $clamped['perPage']);
        self::assertCount(1, $this->rows($clamped['items']));
        self::assertSame(3, $clamped['total']);
        self::assertSame(3, $clamped['pages']);

        $this->api($client, 'GET', '/admin/api/leads?page=abc&perPage=xyz');
        self::assertResponseStatusCodeSame(400);
        self::assertSame('BAD_REQUEST', $this->json($client)['code']);
    }

    public function testPageBeyondLastReturnsEmptyListWithTotals(): void
    {
        $client = $this->adminClient();
        $this->seedLeads(3);

        $this->api($client, 'GET', '/admin/api/leads?perPage=2&page=50');

        self::assertResponseIsSuccessful();
        $payload = $this->json($client);
        self::assertSame([], $payload['items']);
        self::assertSame(3, $payload['total']);
        self::assertSame(2, $payload['pages']);
    }

    public function testEmptyDatabaseReturnsEmptyListAndZeroSummary(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'GET', '/admin/api/leads');
        self::assertResponseIsSuccessful();
        $payload = $this->json($client);
        self::assertSame([], $payload['items']);
        self::assertSame(0, $payload['total']);

        $this->api($client, 'GET', '/admin/api/leads/summary');
        self::assertResponseIsSuccessful();
    }

    public function testUnknownSortFallsBackAndDirectionAscReversesOrder(): void
    {
        $client = $this->adminClient();
        $this->seedLeads(3);

        $this->api($client, 'GET', '/admin/api/leads?sort=password');
        self::assertResponseIsSuccessful();
        self::assertSame(['Клиент 3', 'Клиент 2', 'Клиент 1'], $this->names($this->json($client)));

        $this->api($client, 'GET', '/admin/api/leads?direction=asc');
        self::assertSame(['Клиент 1', 'Клиент 2', 'Клиент 3'], $this->names($this->json($client)));
    }

    public function testStatusAllAndAssigneeAllMeanNoFilter(): void
    {
        $client = $this->adminClient();
        $this->seedLeads(3);

        $this->api($client, 'GET', '/admin/api/leads?status=all&assignee=all&source=');

        self::assertResponseIsSuccessful();
        self::assertSame(3, $this->json($client)['total']);
    }

    public function testSearchTreatsLikeWildcardsAsPlainText(): void
    {
        $client = $this->adminClient();
        $this->seedLeads(2);

        foreach (['%', '_', "' OR 1=1 --"] as $query) {
            $this->api($client, 'GET', '/admin/api/leads?q='.rawurlencode($query));
            self::assertResponseIsSuccessful();
            self::assertSame(0, $this->json($client)['total'], 'Запрос «'.$query.'» не должен совпадать со всеми заявками.');
        }
    }

    public function testDateRangeIncludesBothBoundaryDays(): void
    {
        $client = $this->adminClient();
        $this->seedLeads(3);

        $this->api($client, 'GET', '/admin/api/leads?from=2026-09-02&to=2026-09-02');
        self::assertResponseIsSuccessful();
        self::assertSame(['Клиент 2'], $this->names($this->json($client)));

        $this->api($client, 'GET', '/admin/api/leads?from=2026-09-03&to=2026-09-01');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->json($client)['total']);

        foreach (['2026-02-30', '2026-13-01', 'yesterday', '01.09.2026'] as $date) {
            $this->api($client, 'GET', '/admin/api/leads?from='.rawurlencode($date));
            self::assertResponseStatusCodeSame(422, $date);
            self::assertSame('VALIDATION', $this->json($client)['code'], $date);
        }
    }

    #[DataProvider('statusTransitions')]
    public function testEveryStatusCanMoveToEveryOtherStatus(string $from, string $to): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads(1, $from)[0];

        $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/status', ['status' => $to]);

        self::assertResponseIsSuccessful();
        $card = $this->json($client);
        self::assertSame($to, $card['status']);
        $events = $this->rows($card['events']);
        if ($from === $to) {
            self::assertSame([], $events, 'Тот же статус не должен писать событие.');

            return;
        }

        self::assertCount(1, $events);
        self::assertSame(['from' => $from, 'to' => $to], $events[0]['data']);

        $this->api($client, 'GET', '/admin/api/leads?status='.$to);
        self::assertSame(1, $this->json($client)['total']);
        $this->api($client, 'GET', '/admin/api/leads?status='.$from);
        self::assertSame(0, $this->json($client)['total']);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function statusTransitions(): iterable
    {
        foreach (LeadStatus::values() as $from) {
            foreach (LeadStatus::values() as $to) {
                yield $from.' -> '.$to => [$from, $to];
            }
        }
    }

    public function testStatusIsNormalizedAndInvalidTypesAreValidationErrors(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads(1)[0];
        $uri = '/admin/api/leads/'.$lead->id().'/status';

        $this->api($client, 'PATCH', $uri, ['status' => '  DONE ']);
        self::assertResponseIsSuccessful();
        self::assertSame('done', $this->json($client)['status']);

        foreach ([['status' => 5], ['status' => ['done']], ['status' => ''], ['status' => null], ['status' => 'all']] as $payload) {
            $this->api($client, 'PATCH', $uri, $payload);
            self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
            self::assertSame('VALIDATION', $this->json($client)['code']);
        }

        $this->api($client, 'GET', '/admin/api/leads/'.$lead->id());
        self::assertSame('done', $this->json($client)['status'], 'Невалидные запросы не меняют статус.');
    }

    public function testMalformedJsonBodyDoesNotLeakParserDetails(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads(1)[0];

        $this->api($client, 'GET', '/admin/dashboard');
        $client->request('PATCH', '/admin/api/leads/'.$lead->id().'/status', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $this->csrfFrom($client),
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ], content: '{"status": "do');

        self::assertContains($client->getResponse()->getStatusCode(), [400, 422]);
        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Syntax error', $content);
        self::assertStringNotContainsString('Unexpected', $content);
        self::assertStringNotContainsString('.php', $content);
    }

    public function testNoteLengthBoundaryAndInvalidTypes(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads(1)[0];
        $uri = '/admin/api/leads/'.$lead->id().'/notes';

        $this->api($client, 'POST', $uri, ['text' => str_repeat('я', LeadWorkflow::NOTE_MAX_LENGTH)]);
        self::assertResponseStatusCodeSame(201);

        $this->api($client, 'POST', $uri, ['text' => str_repeat('я', LeadWorkflow::NOTE_MAX_LENGTH + 1)]);
        self::assertResponseStatusCodeSame(422);

        foreach ([[], ['text' => null], ['text' => 123], ['text' => ['a']]] as $payload) {
            $this->api($client, 'POST', $uri, $payload);
            self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        }

        $this->api($client, 'GET', '/admin/api/leads/'.$lead->id());
        self::assertCount(1, $this->rows($this->json($client)['events']), 'Невалидные заметки не должны попадать в историю.');
    }

    public function testNoteWithHtmlIsStoredAsPlainText(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads(1)[0];

        $this->api($client, 'POST', '/admin/api/leads/'.$lead->id().'/notes', ['text' => '<script>alert(1)</script>']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        $events = $this->rows($this->json($client)['events']);
        self::assertSame('<script>alert(1)</script>', $events[0]['body']);
    }

    public function testAssigningUnknownOrMalformedUserIsRejectedAndKeepsAssignee(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads(1)[0];
        $uri = '/admin/api/leads/'.$lead->id().'/assignee';

        foreach (['01ARZ3NDEKTSV4RRFFQ69G5FAV', 'not-a-ulid', ''] as $assigneeId) {
            $this->api($client, 'PATCH', $uri, ['assigneeId' => $assigneeId]);
            self::assertResponseStatusCodeSame(422, $assigneeId);
            self::assertSame('VALIDATION', $this->json($client)['code'], $assigneeId);
        }

        $this->api($client, 'GET', '/admin/api/leads/'.$lead->id());
        self::assertNull($this->json($client)['assignee']);
    }

    public function testClearingAlreadyEmptyAssigneeDoesNotWriteHistory(): void
    {
        $client = $this->adminClient();
        $lead = $this->seedLeads(1)[0];

        $this->api($client, 'PATCH', '/admin/api/leads/'.$lead->id().'/assignee', ['assigneeId' => null]);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->rows($this->json($client)['events']));
    }

    public function testMutationsOnUnknownLeadReturnNotFoundWithoutSideEffects(): void
    {
        $client = $this->adminClient();
        $unknown = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        $this->api($client, 'PATCH', "/admin/api/leads/{$unknown}/status", ['status' => 'done']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('NOT_FOUND', $this->json($client)['code']);

        $this->api($client, 'POST', "/admin/api/leads/{$unknown}/notes", ['text' => 'x']);
        self::assertResponseStatusCodeSame(404);

        $this->api($client, 'PATCH', "/admin/api/leads/{$unknown}/assignee", ['assigneeId' => null]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testExportWithoutLeadsReturnsHeaderOnlyCsv(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'GET', '/admin/api/leads/export');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $client->getResponse()->headers->get('Content-Type'));
        $lines = array_values(array_filter(explode("\n", trim((string) $client->getResponse()->getContent()))));
        self::assertCount(1, $lines, 'Пустая выгрузка содержит только строку заголовков.');
    }

    public function testExportNeutralizesSpreadsheetFormulas(): void
    {
        $client = $this->adminClient();
        $entityManager = $this->entityManager();
        $entityManager->persist(new Lead('callback', '=HYPERLINK("http://evil.test")', '+7 900 000-00-00', null, '@SUM(A1:A9)', ['consent' => true]));
        $entityManager->flush();

        $this->api($client, 'GET', '/admin/api/leads/export');

        self::assertResponseIsSuccessful();
        $csv = (string) $client->getResponse()->getContent();
        self::assertDoesNotMatchRegularExpression('/(^|[,;"\n])=HYPERLINK/', $csv);
        self::assertDoesNotMatchRegularExpression('/(^|[,;"\n])@SUM/', $csv);
    }

    private function csrfFrom(KernelBrowser $client): string
    {
        $client->request('GET', '/admin/dashboard');
        preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', (string) $client->getResponse()->getContent(), $matches);

        return $matches[1] ?? '';
    }

    /**
     * @return list<Lead>
     */
    private function seedLeads(int $count, string $status = LeadStatus::NEW): array
    {
        $entityManager = $this->entityManager();
        $leads = [];
        for ($index = 1; $index <= $count; ++$index) {
            $lead = new Lead('callback', 'Клиент '.$index, '+7 900 000-00-0'.$index, null, 'Сообщение '.$index, ['consent' => true]);
            if (LeadStatus::NEW !== $status) {
                $lead->updateStatus($status);
            }
            $entityManager->persist($lead);
            $entityManager->flush();
            $entityManager->getConnection()->executeStatement(
                'UPDATE leads SET created_at = :createdAt WHERE id = :id',
                ['createdAt' => \sprintf('2026-09-0%d 10:00:00', $index), 'id' => $lead->id()->toBinary()],
            );
            $leads[] = $lead;
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
}
