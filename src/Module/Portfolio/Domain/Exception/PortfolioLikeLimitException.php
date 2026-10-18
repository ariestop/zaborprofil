<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Exception;

use RuntimeException;

/**
 * С одного IP на работу уже поставлено слишком много лайков (новые cookie не помогают накрутить счётчик).
 */
final class PortfolioLikeLimitException extends RuntimeException
{
}
