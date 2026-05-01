<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class ArchivePageCommand
{
    public function __construct(public string $id)
    {
    }
}
