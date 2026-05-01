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
        $roles = $this->roles;
        $roles[] = 'ROLE_ADMIN';

        return array_values(array_unique($roles));
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
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
