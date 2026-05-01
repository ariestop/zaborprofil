<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

final class ContentId
{
    public function fromString(string $id): Ulid
    {
        if (!Ulid::isValid($id)) {
            throw new InvalidArgumentException('Invalid content id.');
        }

        return Ulid::fromString($id);
    }
}
