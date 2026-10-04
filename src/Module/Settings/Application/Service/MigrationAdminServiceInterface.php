<?php

declare(strict_types=1);

namespace App\Module\Settings\Application\Service;

interface MigrationAdminServiceInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array;

    /**
     * @return array{status: string, executedCount: int}
     */
    public function apply(string $version): array;

    /**
     * @return array{status: string, executedCount: int}
     */
    public function rollback(string $version): array;
}
