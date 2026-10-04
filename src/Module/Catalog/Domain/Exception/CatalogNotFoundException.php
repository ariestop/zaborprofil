<?php

declare(strict_types=1);

namespace App\Module\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundExceptionInterface;
use RuntimeException;

final class CatalogNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
