<?php

declare(strict_types=1);

namespace App\Configurator\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'configurations')]
#[ORM\Index(columns: ['guest_token'], name: 'idx_configurations_guest')]
class Configuration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id', type: Types::BIGINT, nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(name: 'guest_token', length: 255, nullable: true)]
    private ?string $guestToken = null;

    #[ORM\Column(name: 'config_data', type: Types::JSON)]
    private array $configData = [];

    #[ORM\Column(name: 'version', type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getGuestToken(): ?string
    {
        return $this->guestToken;
    }

    public function setGuestToken(?string $guestToken): self
    {
        $this->guestToken = $guestToken;
        return $this;
    }

    public function getConfigData(): array
    {
        return $this->configData;
    }

    public function setConfigData(array $configData): self
    {
        $this->configData = $configData;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
