<?php

declare(strict_types=1);

namespace App\Estimation\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'estimate_lines')]
class EstimateLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'estimate_id', type: Types::BIGINT)]
    private int $estimateId;

    #[ORM\ManyToOne(targetEntity: Estimate::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'estimate_id', referencedColumnName: 'id')]
    private Estimate $estimate;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEstimateId(): int
    {
        return $this->estimateId;
    }

    public function getEstimate(): Estimate
    {
        return $this->estimate;
    }

    public function setEstimate(Estimate $estimate): self
    {
        $this->estimate = $estimate;
        $this->estimateId = $estimate->getId() ?? 0;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }
}
