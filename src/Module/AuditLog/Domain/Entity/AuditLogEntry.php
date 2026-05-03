<?php

declare(strict_types=1);

namespace App\Module\AuditLog\Domain\Entity;

use App\Module\AuditLog\Infrastructure\Doctrine\Repository\DoctrineAuditLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: DoctrineAuditLogRepository::class)]
#[ORM\Table(name: 'audit_log_entries')]
#[ORM\Index(name: 'idx_audit_log_entries_occurred_at', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_audit_log_entries_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_audit_log_entries_actor', columns: ['actor_id'])]
final class AuditLogEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column]
    private DateTimeImmutable $occurredAt;

    #[ORM\Column(type: 'ulid', nullable: true)]
    private ?Ulid $actorId;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorEmail;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ip;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $requestId;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 160)]
    private string $entityType;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entityId;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $oldValues;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $newValues;

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public function __construct(
        string $action,
        string $entityType,
        ?string $entityId,
        array $oldValues,
        array $newValues,
        ?Ulid $actorId = null,
        ?string $actorEmail = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $requestId = null,
    ) {
        $this->id = new Ulid();
        $this->occurredAt = new DateTimeImmutable();
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->oldValues = $oldValues;
        $this->newValues = $newValues;
        $this->actorId = $actorId;
        $this->actorEmail = $actorEmail;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->requestId = $requestId;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function actorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function actorId(): ?Ulid
    {
        return $this->actorId;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): ?string
    {
        return $this->entityId;
    }

    /**
     * @return array<string, mixed>
     */
    public function oldValues(): array
    {
        return $this->oldValues;
    }

    /**
     * @return array<string, mixed>
     */
    public function newValues(): array
    {
        return $this->newValues;
    }
}
