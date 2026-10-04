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
use App\Module\Lead\Domain\ValueObject\PhoneNumber;
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

    public const string MANUAL_SOURCE = 'phone_call';

    /**
     * Заявка, которую менеджер завёл сам, например после телефонного звонка.
     * Согласие на обработку данных при этом не фиксируется, и карточка показывает это явно.
     */
    public function createManual(string $name, string $phone, ?string $email, ?string $message, LeadActor $actor): Lead
    {
        $name = trim($name);
        $phone = trim($phone);
        $email = $email === null ? null : trim($email);
        if ($name === '' || mb_strlen($name) > 180) {
            throw new InvalidArgumentException('Lead name must be between 1 and 180 characters.');
        }
        if (mb_strlen($phone) > 40 || \strlen(PhoneNumber::digits($phone)) < 6) {
            throw new InvalidArgumentException('Lead phone must contain at least 6 digits.');
        }
        if ($email !== null && $email !== '' && (mb_strlen($email) > 180 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('Lead email is not valid.');
        }
        if ($message !== null && mb_strlen($message) > self::NOTE_MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Lead message must not exceed %d characters.', self::NOTE_MAX_LENGTH));
        }

        $lead = new Lead(self::MANUAL_SOURCE, $name, $phone, $email, $message, ['consent' => false, 'origin' => 'manual']);
        $lead->markRead();
        $this->leads->save($lead);

        $leadId = (string) $lead->id();
        $this->audit->log('lead.created_manually', 'lead', $leadId, [], ['source' => self::MANUAL_SOURCE]);
        $this->businessEvents->log('lead.created', [
            'leadId' => $leadId,
            'source' => self::MANUAL_SOURCE,
            'status' => $lead->status(),
            'maskedContact' => PhoneNumber::mask($phone),
        ]);

        return $lead;
    }

    public function markRead(Lead $lead): Lead
    {
        $lead->markRead();
        $this->leads->save($lead);

        return $lead;
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
