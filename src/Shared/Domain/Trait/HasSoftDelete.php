<?php

declare(strict_types=1);

namespace App\Shared\Domain\Trait;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait HasSoftDelete
{
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function markDeleted(): void
    {
        $this->deletedAt = new DateTimeImmutable();
    }
}
