<?php

declare(strict_types=1);

namespace App\Estimation\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity]
#[ORM\Table(name: 'estimates')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_estimates_user_created')]
class Estimate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id', type: Types::BIGINT, nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(name: 'configuration_id', type: Types::BIGINT)]
    private int $configurationId;

    #[ORM\Column(name: 'total_price', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $totalPrice;

    #[ORM\Column(length: 50, options: ['default' => 'draft'])]
    private string $status = 'draft';

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, EstimateLine> */
    #[ORM\OneToMany(targetEntity: EstimateLine::class, mappedBy: 'estimate', cascade: ['persist'])]
    private Collection $lines;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
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

    public function getConfigurationId(): int
    {
        return $this->configurationId;
    }

    public function setConfigurationId(int $configurationId): self
    {
        $this->configurationId = $configurationId;
        return $this;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, EstimateLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(EstimateLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setEstimate($this);
        }
        return $this;
    }
}
