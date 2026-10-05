<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\Entity;

use App\Module\Lead\Domain\ValueObject\LeadKind;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use App\Module\Lead\Domain\ValueObject\LeadUtm;
use App\Module\Lead\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Lead\Infrastructure\Doctrine\Repository\DoctrineLeadRepository::class)]
#[ORM\Table(name: 'leads')]
#[ORM\Index(name: 'idx_leads_status_created_at', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'idx_leads_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_leads_source', columns: ['source'])]
#[ORM\Index(name: 'idx_leads_assignee', columns: ['assignee_id'])]
#[ORM\Index(name: 'idx_leads_phone_digits', columns: ['phone_digits'])]
final class Lead
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 120)]
    private string $source;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 40)]
    private string $phone;

    #[ORM\Column(name: 'phone_digits', length: 40)]
    private string $phoneDigits;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $consentSnapshot;

    #[ORM\Column(length: 32)]
    private string $status = LeadStatus::NEW;

    #[ORM\Column(name: 'spam_score')]
    private int $spamScore = 0;

    /**
     * @var list<string>
     */
    #[ORM\Column(name: 'spam_reasons', type: 'json')]
    private array $spamReasons = [];

    #[ORM\Column(name: 'assignee_id', type: 'ulid', nullable: true)]
    private ?Ulid $assigneeId = null;

    #[ORM\Column(name: 'page_url', length: 500, nullable: true)]
    private ?string $pageUrl;

    /**
     * @var array<string, string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $utm;

    #[ORM\Column(name: 'is_b2b', options: ['default' => 0])]
    private bool $b2b;

    #[ORM\Column(name: 'read_at', nullable: true)]
    private ?DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $consentSnapshot
     * @param array<string, mixed> $utm
     */
    public function __construct(string $source, string $name, string $phone, ?string $email, ?string $message, array $consentSnapshot, ?string $pageUrl = null, array $utm = [])
    {
        $this->id = new Ulid();
        $this->source = self::required($source, 'Lead source cannot be empty.');
        $this->name = self::required($name, 'Lead name cannot be empty.');
        $this->phone = self::required($phone, 'Lead phone cannot be empty.');
        $this->phoneDigits = PhoneNumber::digits($this->phone);
        $this->email = self::optional($email);
        $this->message = self::optional($message);
        $this->consentSnapshot = $consentSnapshot;
        $pageUrl = self::optional($pageUrl);
        $this->pageUrl = $pageUrl === null ? null : mb_substr($pageUrl, 0, 500);
        $utm = LeadUtm::sanitize($utm);
        $this->utm = $utm === [] ? null : $utm;
        $this->b2b = LeadKind::isCompany($this->name);
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateStatus(string $status): void
    {
        $this->status = LeadStatus::normalize($status);
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markRead(): void
    {
        $this->readAt ??= new DateTimeImmutable();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function phoneDigits(): string
    {
        return $this->phoneDigits;
    }

    public function assigneeId(): ?Ulid
    {
        return $this->assigneeId;
    }

    public function assignTo(?Ulid $assigneeId): void
    {
        $this->assigneeId = $assigneeId;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @param list<string> $reasons
     */
    public function markSpam(int $score, array $reasons): void
    {
        if ($score < 0) {
            throw new InvalidArgumentException('Lead spam score must be zero or positive.');
        }

        $this->spamScore = $score;
        $this->spamReasons = array_values(array_filter($reasons, static fn (string $reason): bool => trim($reason) !== ''));
        $this->updateStatus(LeadStatus::SPAM);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'source' => $this->source,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'message' => $this->message,
            'consentSnapshot' => $this->consentSnapshot,
            'status' => $this->status,
            'assigneeId' => $this->assigneeId === null ? null : (string) $this->assigneeId,
            'spamScore' => $this->spamScore,
            'spamReasons' => $this->spamReasons,
            'pageUrl' => $this->pageUrl,
            'utm' => $this->utm ?? [],
            'b2b' => $this->b2b,
            'readAt' => $this->readAt?->format(DATE_ATOM),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private static function required(string $value, string $message): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException($message);
        }

        return $normalized;
    }

    private static function optional(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
