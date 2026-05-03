<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\Entity;

use App\Module\Lead\Domain\ValueObject\LeadStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Lead\Infrastructure\Doctrine\Repository\DoctrineLeadRepository::class)]
#[ORM\Table(name: 'leads')]
#[ORM\Index(name: 'idx_leads_status_created_at', columns: ['status', 'created_at'])]
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

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $consentSnapshot;

    #[ORM\Column(length: 32)]
    private string $status = LeadStatus::NEW;

    #[ORM\Column(name: 'spam_score')]
    private int $spamScore = 0;

    /**
     * @var list<string>
     */
    #[ORM\Column(name: 'spam_reasons', type: 'json', options: ['jsonb' => true])]
    private array $spamReasons = [];

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $consentSnapshot
     */
    public function __construct(string $source, string $name, string $phone, ?string $email, ?string $message, array $consentSnapshot)
    {
        $this->id = new Ulid();
        $this->source = self::required($source, 'Lead source cannot be empty.');
        $this->name = self::required($name, 'Lead name cannot be empty.');
        $this->phone = self::required($phone, 'Lead phone cannot be empty.');
        $this->email = self::optional($email);
        $this->message = self::optional($message);
        $this->consentSnapshot = $consentSnapshot;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateStatus(string $status): void
    {
        $this->status = LeadStatus::normalize($status);
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
            'spamScore' => $this->spamScore,
            'spamReasons' => $this->spamReasons,
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
