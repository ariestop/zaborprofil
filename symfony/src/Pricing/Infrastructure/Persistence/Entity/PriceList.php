<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'price_lists')]
class PriceList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'price_profile_id', type: Types::BIGINT)]
    private int $priceProfileId;

    #[ORM\ManyToOne(targetEntity: PriceProfile::class)]
    #[ORM\JoinColumn(name: 'price_profile_id', referencedColumnName: 'id')]
    private PriceProfile $priceProfile;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = 'RUB';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPriceProfileId(): int
    {
        return $this->priceProfileId;
    }

    public function getPriceProfile(): PriceProfile
    {
        return $this->priceProfile;
    }

    public function setPriceProfile(PriceProfile $priceProfile): self
    {
        $this->priceProfile = $priceProfile;
        $this->priceProfileId = $priceProfile->getId() ?? 0;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }
}
