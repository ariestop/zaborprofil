<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * Validates a candidate identifier string for the Content module and returns
 * the canonical form. Application code passes around plain strings; only the
 * Doctrine adapter converts to a Symfony `Ulid` for the database.
 */
final class ContentId
{
    public function fromString(string $id): string
    {
        if (!Ulid::isValid($id)) {
            throw new InvalidArgumentException('Invalid content id.');
        }

        return (string) Ulid::fromString($id);
    }
}
