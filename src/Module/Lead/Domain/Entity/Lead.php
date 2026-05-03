<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\Entity;

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
    private string $status = 'new';

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
        $allowed = ['new', 'in_progress', 'done', 'spam'];
        if (!\in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Lead status is not allowed.');
        }

        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
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
