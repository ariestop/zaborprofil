<?php

declare(strict_types=1);

namespace App\Module\Lead\UI\Admin;

use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Entity\LeadEvent;
use App\Module\Lead\Domain\Repository\LeadAssigneeDirectoryInterface;
use App\Module\Lead\Domain\ValueObject\LeadAssignee;
use App\Module\Lead\Domain\ValueObject\LeadEventType;

final readonly class LeadPresenter
{
    private const int PREVIEW_LENGTH = 140;

    public function __construct(private LeadAssigneeDirectoryInterface $assignees)
    {
    }

    /**
     * @param list<Lead> $leads
     *
     * @return list<array<string, mixed>>
     */
    public function list(array $leads): array
    {
        $people = $this->assigneesOf($leads);

        return array_map(function (Lead $lead) use ($people): array {
            $payload = $lead->toArray();
            $message = \is_string($payload['message']) ? $payload['message'] : null;

            return [
                'id' => $payload['id'],
                'source' => $payload['source'],
                'name' => $payload['name'],
                'phone' => $payload['phone'],
                'email' => $payload['email'],
                'status' => $payload['status'],
                'assignee' => self::assignee($payload['assigneeId'], $people),
                'spamScore' => $payload['spamScore'],
                'b2b' => $payload['b2b'],
                'pageUrl' => $payload['pageUrl'],
                'readAt' => $payload['readAt'],
                'messagePreview' => $message === null ? null : mb_strimwidth($message, 0, self::PREVIEW_LENGTH, '…'),
                'createdAt' => $payload['createdAt'],
                'updatedAt' => $payload['updatedAt'],
            ];
        }, $leads);
    }

    /**
     * Карточка заявки. Авторы событий и ответственные показываются по текущему профилю
     * («Фамилия Имя»), а сохранённая в событии подпись остаётся запасной — для удалённых
     * пользователей.
     *
     * @param list<LeadEvent> $events
     *
     * @return array<string, mixed>
     */
    public function detail(Lead $lead, array $events = []): array
    {
        $payload = $lead->toArray();
        $eventPayloads = array_map(static fn (LeadEvent $event): array => $event->toArray(), $events);

        $ids = [];
        if (\is_string($payload['assigneeId'])) {
            $ids[$payload['assigneeId']] = true;
        }
        foreach ($eventPayloads as $event) {
            foreach ([$event['actorId'], self::assignedTo($event)] as $id) {
                if (\is_string($id)) {
                    $ids[$id] = true;
                }
            }
        }
        $people = $this->assignees->findMany(array_keys($ids));

        $payload['assignee'] = self::assignee($payload['assigneeId'], $people);
        unset($payload['assigneeId']);
        $payload['events'] = array_map(static fn (array $event): array => self::event($event, $people), $eventPayloads);

        return $payload;
    }

    /**
     * Подписи ответственных для выгрузки CSV.
     *
     * @param list<Lead> $leads
     *
     * @return array<string, string>
     */
    public function labels(array $leads): array
    {
        return array_map(static fn (LeadAssignee $assignee): string => $assignee->label(), $this->assigneesOf($leads));
    }

    /**
     * @param list<Lead> $leads
     *
     * @return array<string, LeadAssignee>
     */
    private function assigneesOf(array $leads): array
    {
        $ids = [];
        foreach ($leads as $lead) {
            if ($lead->assigneeId() !== null) {
                $ids[(string) $lead->assigneeId()] = true;
            }
        }

        return $this->assignees->findMany(array_keys($ids));
    }

    /**
     * @param array<string, LeadAssignee> $people
     *
     * @return array{id: string, email: string|null, name: string|null}|null
     */
    private static function assignee(mixed $assigneeId, array $people): ?array
    {
        if (!\is_string($assigneeId)) {
            return null;
        }

        $person = $people[$assigneeId] ?? null;

        return ['id' => $assigneeId, 'email' => $person?->email, 'name' => $person?->name];
    }

    /**
     * @param array<string, mixed>        $event
     * @param array<string, LeadAssignee> $people
     *
     * @return array<string, mixed>
     */
    private static function event(array $event, array $people): array
    {
        $actor = \is_string($event['actorId']) ? ($people[$event['actorId']] ?? null) : null;
        if ($actor !== null) {
            $event['actorLabel'] = $actor->label();
        }

        $to = self::assignedTo($event);
        if ($to !== null && isset($people[$to]) && \is_array($event['data'])) {
            $event['data']['toLabel'] = $people[$to]->label();
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function assignedTo(array $event): ?string
    {
        if ($event['type'] !== LeadEventType::ASSIGNED || !\is_array($event['data'])) {
            return null;
        }

        $to = $event['data']['to'] ?? null;

        return \is_string($to) ? $to : null;
    }
}
