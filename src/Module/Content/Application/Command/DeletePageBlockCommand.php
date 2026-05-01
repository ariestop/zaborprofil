<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class DeletePageBlockCommand
{
    public function __construct(public string $id)
    {
    }
}
