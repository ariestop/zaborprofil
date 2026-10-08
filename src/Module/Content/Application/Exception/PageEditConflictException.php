<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Exception;

use DateTimeImmutable;
use RuntimeException;

/**
 * Блоки страницы изменил другой редактор после того, как эта правка была начата.
 */
final class PageEditConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $currentVersion,
        public readonly DateTimeImmutable $updatedAt,
    ) {
        parent::__construct('Page blocks were changed by another editor.');
    }
}
