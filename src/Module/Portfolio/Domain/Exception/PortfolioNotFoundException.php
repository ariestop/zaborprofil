<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundExceptionInterface;
use RuntimeException;

final class PortfolioNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
