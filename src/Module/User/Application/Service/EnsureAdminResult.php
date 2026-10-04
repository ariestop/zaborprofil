<?php

declare(strict_types=1);

namespace App\Module\User\Application\Service;

enum EnsureAdminResult: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
}
