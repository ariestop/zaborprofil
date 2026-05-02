<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identifier;

use Stringable;
use Symfony\Component\Uid\Ulid;

final readonly class EntityId implements Stringable
{
    private function __construct(private Ulid $value)
    {
    }

    public static function new(): self
    {
        return new self(new Ulid());
    }

    public static function fromString(string $id): self
    {
        return new self(Ulid::fromString($id));
    }

    public function toUlid(): Ulid
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
