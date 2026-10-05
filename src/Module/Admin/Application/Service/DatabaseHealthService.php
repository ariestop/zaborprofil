<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DatabaseHealthService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $serverVersion = 'unknown';
        $databaseName = 'unknown';

        try {
            $serverVersion = $this->fetchString('SELECT VERSION()', $serverVersion);
            $databaseName = $this->fetchString('SELECT DATABASE()', $databaseName);
        } catch (\Throwable) {
            // Keep graceful response for foundation endpoints.
        }

        return [
            'databaseName' => $databaseName,
            'platform' => $this->connection->getDatabasePlatform()::class,
            'serverVersion' => $serverVersion,
            'connected' => $this->connection->isConnected(),
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    private function fetchString(string $sql, string $fallback): string
    {
        $value = $this->connection->fetchOne($sql);

        return \is_string($value) && $value !== '' ? $value : $fallback;
    }
}
