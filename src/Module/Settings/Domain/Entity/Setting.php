<?php

declare(strict_types=1);

namespace App\Module\Settings\Domain\Entity;

use App\Module\Settings\Infrastructure\Doctrine\Repository\DoctrineSettingRepository;
use App\Shared\Domain\Contract\TimestampedEntityInterface;
use App\Shared\Domain\Trait\HasTimestamps;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: DoctrineSettingRepository::class)]
#[ORM\Table(name: 'settings')]
#[ORM\UniqueConstraint(name: 'uniq_settings_scope_key', columns: ['scope', 'setting_key'])]
#[ORM\Index(name: 'idx_settings_scope', columns: ['scope'])]
class Setting implements TimestampedEntityInterface
{
    use HasTimestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 80)]
    private string $scope;

    #[ORM\Column(name: 'setting_key', length: 120)]
    private string $key;

    #[ORM\Column(name: 'setting_value', type: 'json', options: ['jsonb' => true])]
    private mixed $value;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description;

    public function __construct(string $scope, string $key, mixed $value, ?string $description = null)
    {
        $this->id = new Ulid();
        $this->scope = self::normalizeIdentifier($scope, 'Setting scope');
        $this->key = self::normalizeIdentifier($key, 'Setting key');
        $this->value = $value;
        $this->description = self::normalizeNullable($description);
        $this->initializeTimestamps();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function update(mixed $value, ?string $description = null): void
    {
        $this->value = $value;
        $this->description = self::normalizeNullable($description);
        $this->touch();
    }

    private static function normalizeIdentifier(string $value, string $label): string
    {
        $normalized = trim($value);

        if (!preg_match('/^[a-z][a-z0-9_.-]{1,119}$/', $normalized)) {
            throw new InvalidArgumentException($label.' must start with a lowercase latin letter and contain only lowercase latin letters, numbers, dots, underscores and hyphens.');
        }

        return $normalized;
    }

    private static function normalizeNullable(?string $value): ?string
    {
        $normalized = $value === null ? null : trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
