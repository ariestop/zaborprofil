<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'price_rules')]
#[ORM\Index(columns: ['product_id', 'price_list_id'], name: 'idx_price_rules_product_list')]
class PriceRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'product_id', type: Types::BIGINT)]
    private int $productId;

    #[ORM\Column(name: 'price_list_id', type: Types::BIGINT)]
    private int $priceListId;

    #[ORM\ManyToOne(targetEntity: PriceList::class)]
    #[ORM\JoinColumn(name: 'price_list_id', referencedColumnName: 'id')]
    private PriceList $priceList;

    #[ORM\Column(name: 'base_price', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $basePrice;

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

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): self
    {
        $this->productId = $productId;
        return $this;
    }

    public function getPriceListId(): int
    {
        return $this->priceListId;
    }

    public function getPriceList(): PriceList
    {
        return $this->priceList;
    }

    public function setPriceList(PriceList $priceList): self
    {
        $this->priceList = $priceList;
        $this->priceListId = $priceList->getId() ?? 0;
        return $this;
    }

    public function getBasePrice(): string
    {
        return $this->basePrice;
    }

    public function setBasePrice(string $basePrice): self
    {
        $this->basePrice = $basePrice;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
