<?php

declare(strict_types=1);

namespace App\Module\User\Infrastructure\Doctrine\Entity;

use App\Module\User\Infrastructure\Repository\AdminUserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: AdminUserRepository::class)]
#[ORM\Table(name: 'admin_users')]
#[ORM\UniqueConstraint(name: 'uniq_admin_users_email', columns: ['email'])]
final class AdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column]
    private bool $active = true;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @param list<string> $roles
     */
    public function __construct(string $email, string $passwordHash, array $roles = ['ROLE_ADMIN'])
    {
        $this->id = new Ulid();
        $this->email = self::normalizeEmail($email);
        $this->passwordHash = $passwordHash;
        $this->roles = $roles;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->storedRoles();
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0passwordHash"] = hash('crc32c', $this->passwordHash);

        return $data;
    }

    /**
     * Роли, сохранённые в БД. Совпадает с getRoles(): права строятся только из назначенных ролей и role_hierarchy.
     *
     * @return list<string>
     */
    public function storedRoles(): array
    {
        return array_values(array_unique($this->roles));
    }

    public function changePasswordHash(string $passwordHash): void
    {
        if ($passwordHash === '') {
            throw new InvalidArgumentException('Password hash cannot be empty.');
        }

        $this->passwordHash = $passwordHash;
        $this->touch();
    }

    public function activate(): void
    {
        $this->active = true;
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @param list<string> $roles
     */
    public function updateRoles(array $roles): void
    {
        if ($roles === []) {
            throw new InvalidArgumentException('At least one role is required.');
        }

        $normalizedRoles = array_values(array_unique(array_map(trim(...), $roles)));
        $normalizedRoles = array_values(array_filter($normalizedRoles, static fn (string $role): bool => $role !== ''));

        if ($normalizedRoles === []) {
            throw new InvalidArgumentException('At least one valid role is required.');
        }

        $this->roles = $normalizedRoles;
        $this->touch();
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeEmail(string $email): string
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($normalizedEmail === '') {
            throw new InvalidArgumentException('Admin email cannot be empty.');
        }

        return $normalizedEmail;
    }
}
