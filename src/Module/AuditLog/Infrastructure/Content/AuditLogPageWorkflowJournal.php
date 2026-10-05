<?php

declare(strict_types=1);

namespace App\Module\AuditLog\Infrastructure\Content;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\Module\Content\Application\Journal\PageWorkflowEvent;
use App\Module\Content\Application\Journal\PageWorkflowHistoryEntry;
use App\Module\Content\Application\Journal\PageWorkflowJournalInterface;
use App\Module\Content\Domain\Entity\Page;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Shared\Infrastructure\Http\RequestIdSubscriber;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Пишет события жизненного цикла страницы в общий журнал аудита (`audit_log_entries`)
 * с действием `page.workflow.<event>` и сущностью {@see Page}.
 */
final readonly class AuditLogPageWorkflowJournal implements PageWorkflowJournalInterface
{
    public const string ACTION_PREFIX = 'page.workflow.';
    private const string SYSTEM_ACTOR = 'system';

    public function __construct(
        private AuditLogRepositoryInterface $auditLog,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function record(PageWorkflowEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();
        $admin = $user instanceof AdminUser ? $user : null;

        $this->auditLog->save(new AuditLogEntry(
            action: self::ACTION_PREFIX.$event->name,
            entityType: Page::class,
            entityId: $event->pageId,
            oldValues: ['status' => $event->fromStatus],
            newValues: array_filter([
                'status' => $event->toStatus,
                'path' => $event->path,
                'comment' => $event->comment,
                'details' => $event->details === [] ? null : $event->details,
            ], static fn (mixed $value): bool => $value !== null),
            actorId: $admin?->id(),
            actorEmail: $admin?->email() ?? self::SYSTEM_ACTOR,
            ip: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent'),
            requestId: $request?->attributes->getString(RequestIdSubscriber::ATTRIBUTE) ?: null,
        ));
    }

    public function history(string $pageId, int $limit = 50): array
    {
        $entries = $this->auditLog->findByEntity(Page::class, $pageId, self::ACTION_PREFIX, $limit);

        return array_map(static function (AuditLogEntry $entry): PageWorkflowHistoryEntry {
            $old = $entry->oldValues();
            $new = $entry->newValues();
            $details = [];
            foreach (\is_array($new['details'] ?? null) ? $new['details'] : [] as $key => $value) {
                $details[(string) $key] = $value;
            }

            return new PageWorkflowHistoryEntry(
                (string) $entry->id(),
                substr($entry->action(), \strlen(self::ACTION_PREFIX)),
                $entry->occurredAt()->format(DATE_ATOM),
                $entry->actorEmail() ?? self::SYSTEM_ACTOR,
                \is_string($old['status'] ?? null) ? $old['status'] : null,
                \is_string($new['status'] ?? null) ? $new['status'] : null,
                \is_string($new['comment'] ?? null) ? $new['comment'] : null,
                $details,
            );
        }, $entries);
    }
}
