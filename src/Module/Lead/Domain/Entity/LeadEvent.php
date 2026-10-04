<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\Entity;

use App\Module\Lead\Infrastructure\Doctrine\Repository\DoctrineLeadEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: DoctrineLeadEventRepository::class)]
#[ORM\Table(name: 'lead_events')]
#[ORM\Index(name: 'idx_lead_events_lead_created', columns: ['lead_id', 'created_at'])]
final class LeadEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Lead::class)]
    #[ORM\JoinColumn(name: 'lead_id', nullable: false, onDelete: 'CASCADE')]
    private Lead $lead;

    #[ORM\Column(length: 32)]
    private string $type;

    #[ORM\Column(name: 'actor_id', type: 'ulid', nullable: true)]
    private ?Ulid $actorId;

    #[ORM\Column(name: 'actor_label', length: 180, nullable: true)]
    private ?string $actorLabel;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $data;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(Lead $lead, string $type, ?Ulid $actorId, ?string $actorLabel, ?string $body = null, array $data = [])
    {
        $this->id = new Ulid();
        $this->lead = $lead;
        $this->type = $type;
        $this->actorId = $actorId;
        $this->actorLabel = $actorLabel;
        $this->body = $body;
        $this->data = $data;
        $this->createdAt = new DateTimeImmutable();
    }

    public function lead(): Lead
    {
        return $this->lead;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'actorId' => $this->actorId === null ? null : (string) $this->actorId,
            'actorLabel' => $this->actorLabel,
            'body' => $this->body,
            'data' => $this->data,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
