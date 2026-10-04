<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\Workflow;

use App\Module\Admin\Application\Service\AdminAuditLogger;
use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Entity\LeadEvent;
use App\Module\Lead\Domain\Repository\LeadAssigneeDirectoryInterface;
use App\Module\Lead\Domain\Repository\LeadEventRepositoryInterface;
use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use App\Module\Lead\Domain\ValueObject\LeadActor;
use App\Module\Lead\Domain\ValueObject\LeadEventType;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use App\Shared\Application\Logging\BusinessEventLogger;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * Действия менеджера над заявкой. Каждое изменение пишется в хронологию заявки и в журнал аудита;
 * в аудит и в логи не попадают телефон, email и текст заметок.
 */
final readonly class LeadWorkflow
{
    public const int NOTE_MAX_LENGTH = 2000;

    public function __construct(
        private LeadRepositoryInterface $leads,
        private LeadEventRepositoryInterface $events,
        private LeadAssigneeDirectoryInterface $assignees,
        private AdminAuditLogger $audit,
        private BusinessEventLogger $businessEvents,
    ) {
    }

    public function changeStatus(Lead $lead, string $status, LeadActor $actor): Lead
    {
        $newStatus = LeadStatus::normalize($status);
        $oldStatus = $lead->status();
        if ($newStatus === $oldStatus) {
            return $lead;
        }

        $lead->updateStatus($newStatus);
        $this->leads->save($lead);
        $this->events->save(new LeadEvent(
            $lead,
            LeadEventType::STATUS_CHANGED,
            self::actorId($actor),
            $actor->label,
            null,
            ['from' => $oldStatus, 'to' => $newStatus],
        ));

        $leadId = (string) $lead->id();
        $this->audit->log('lead.status_changed', 'lead', $leadId, ['status' => $oldStatus], ['status' => $newStatus]);
        $this->businessEvents->log('lead.status_changed', ['leadId' => $leadId, 'from' => $oldStatus, 'to' => $newStatus]);

        return $lead;
    }

    public function addNote(Lead $lead, string $text, LeadActor $actor): LeadEvent
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('Note text cannot be empty.');
        }
        if (mb_strlen($text) > self::NOTE_MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Note text must not exceed %d characters.', self::NOTE_MAX_LENGTH));
        }

        $event = new LeadEvent($lead, LeadEventType::NOTE, self::actorId($actor), $actor->label, $text);
        $this->events->save($event);

        $leadId = (string) $lead->id();
        $this->audit->log('lead.note_added', 'lead', $leadId, [], ['length' => mb_strlen($text)]);
        $this->businessEvents->log('lead.note_added', ['leadId' => $leadId]);

        return $event;
    }

    public function assign(Lead $lead, ?string $assigneeId, LeadActor $actor): Lead
    {
        $assignee = null;
        if ($assigneeId !== null) {
            $assignee = $this->assignees->findAssignable($assigneeId)
                ?? throw new InvalidArgumentException('Assignee must be an active user who can work with leads.');
        }

        $newId = $assignee === null ? null : $assignee->id;
        $oldId = $lead->assigneeId() === null ? null : (string) $lead->assigneeId();
        if ($newId === $oldId) {
            return $lead;
        }

        $lead->assignTo($assignee === null ? null : Ulid::fromString($assignee->id));
        $this->leads->save($lead);
        $this->events->save(new LeadEvent(
            $lead,
            LeadEventType::ASSIGNED,
            self::actorId($actor),
            $actor->label,
            null,
            ['from' => $oldId, 'to' => $newId, 'toLabel' => $assignee?->email],
        ));

        $leadId = (string) $lead->id();
        $this->audit->log('lead.assigned', 'lead', $leadId, ['assigneeId' => $oldId], ['assigneeId' => $newId]);
        $this->businessEvents->log('lead.assigned', ['leadId' => $leadId, 'assigneeId' => $newId]);

        return $lead;
    }

    private static function actorId(LeadActor $actor): ?Ulid
    {
        return $actor->id !== null && Ulid::isValid($actor->id) ? Ulid::fromString($actor->id) : null;
    }
}
