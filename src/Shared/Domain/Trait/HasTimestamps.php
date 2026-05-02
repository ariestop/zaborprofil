<?php

declare(strict_types=1);

namespace App\Shared\Domain\Trait;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait HasTimestamps
{
    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function initializeTimestamps(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
