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
            $serverVersion = (string) $this->connection->fetchOne('SELECT version()');
            $databaseName = (string) $this->connection->fetchOne('SELECT current_database()');
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
}
