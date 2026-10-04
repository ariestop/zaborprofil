<?php

declare(strict_types=1);

namespace App\Module\Lead\UI\Admin;

use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Entity\LeadEvent;
use App\Module\Lead\Domain\Repository\LeadAssigneeDirectoryInterface;
use App\Module\Lead\Domain\ValueObject\LeadAssignee;

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
        $labels = $this->labels($leads);

        return array_map(function (Lead $lead) use ($labels): array {
            $payload = $lead->toArray();
            $message = \is_string($payload['message']) ? $payload['message'] : null;

            return [
                'id' => $payload['id'],
                'source' => $payload['source'],
                'name' => $payload['name'],
                'phone' => $payload['phone'],
                'email' => $payload['email'],
                'status' => $payload['status'],
                'assignee' => self::assignee($payload['assigneeId'], $labels),
                'spamScore' => $payload['spamScore'],
                'messagePreview' => $message === null ? null : mb_strimwidth($message, 0, self::PREVIEW_LENGTH, '…'),
                'createdAt' => $payload['createdAt'],
                'updatedAt' => $payload['updatedAt'],
            ];
        }, $leads);
    }

    /**
     * @param list<LeadEvent> $events
     *
     * @return array<string, mixed>
     */
    public function detail(Lead $lead, array $events = []): array
    {
        $payload = $lead->toArray();
        $labels = $this->labels([$lead]);
        $payload['assignee'] = self::assignee($payload['assigneeId'], $labels);
        unset($payload['assigneeId']);
        $payload['events'] = array_map(static fn (LeadEvent $event): array => $event->toArray(), $events);

        return $payload;
    }

    /**
     * @param list<Lead> $leads
     *
     * @return array<string, string>
     */
    public function labels(array $leads): array
    {
        $ids = [];
        foreach ($leads as $lead) {
            if ($lead->assigneeId() !== null) {
                $ids[(string) $lead->assigneeId()] = true;
            }
        }

        return array_map(
            static fn (LeadAssignee $assignee): string => $assignee->email,
            $this->assignees->findMany(array_keys($ids)),
        );
    }

    /**
     * @param array<string, string> $labels
     *
     * @return array{id: string, email: string|null}|null
     */
    private static function assignee(mixed $assigneeId, array $labels): ?array
    {
        if (!\is_string($assigneeId)) {
            return null;
        }

        return ['id' => $assigneeId, 'email' => $labels[$assigneeId] ?? null];
    }
}
