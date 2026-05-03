<?php

declare(strict_types=1);

namespace App\Module\AuditLog\Infrastructure\Doctrine;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Settings\Domain\Entity\Setting;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Shared\Infrastructure\Http\RequestIdSubscriber;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;

#[AsDoctrineListener(event: Events::onFlush)]
final readonly class AuditLogSubscriber
{
    /**
     * @var list<class-string>
     */
    private const array AUDITED_CLASSES = [
        Page::class,
        PageBlock::class,
        Setting::class,
        Redirect::class,
    ];

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata(AuditLogEntry::class);

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $entry = $this->entryFor($entity, 'create', [], $this->snapshot($entity));
            if ($entry !== null) {
                $entityManager->persist($entry);
                $unitOfWork->computeChangeSet($metadata, $entry);
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            $oldValues = [];
            $newValues = [];

            foreach ($changeSet as $field => $change) {
                $oldValues[$field] = $this->normalizeValue($change[0]);
                $newValues[$field] = $this->normalizeValue($change[1]);
            }

            $entry = $this->entryFor($entity, 'update', $oldValues, $newValues);
            if ($entry !== null && $newValues !== []) {
                $entityManager->persist($entry);
                $unitOfWork->computeChangeSet($metadata, $entry);
            }
        }
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    private function entryFor(object $entity, string $action, array $oldValues, array $newValues): ?AuditLogEntry
    {
        if (!\in_array($entity::class, self::AUDITED_CLASSES, true)) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();
        $actorId = $user instanceof AdminUser ? $user->id() : null;
        $actorEmail = $user instanceof AdminUser ? $user->email() : null;

        return new AuditLogEntry(
            $action,
            $entity::class,
            $this->entityId($entity),
            $oldValues,
            $newValues,
            $actorId instanceof Ulid ? $actorId : null,
            $actorEmail,
            $request?->getClientIp(),
            $request?->headers->get('User-Agent'),
            $request?->attributes->getString(RequestIdSubscriber::ATTRIBUTE) ?: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(object $entity): array
    {
        $values = [];
        foreach (['id', 'title', 'path', 'scope', 'key', 'sourcePath', 'targetPath', 'statusCode'] as $method) {
            if (method_exists($entity, $method)) {
                $values[$method] = $this->normalizeValue($entity->{$method}());
            }
        }

        return $values;
    }

    private function entityId(object $entity): ?string
    {
        if (!method_exists($entity, 'id')) {
            return null;
        }

        $id = $entity->id();

        return \is_scalar($id) || $id instanceof \Stringable ? (string) $id : null;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (\is_array($value) || \is_scalar($value) || $value === null) {
            return $value;
        }

        return \is_object($value) ? $value::class : get_debug_type($value);
    }
}
