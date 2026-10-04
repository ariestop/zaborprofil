<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundExceptionInterface;
use RuntimeException;

final class ContentNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
