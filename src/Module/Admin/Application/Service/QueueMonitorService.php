<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class QueueMonitorService
{
    public function __construct(
        private Connection $connection,
        private WhitelistCommandRunner $commandRunner,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return [
            'transports' => [
                'async' => $this->countByQueue('default'),
                'failed' => $this->countByQueue('failed'),
            ],
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function retryFailed(): array
    {
        return $this->commandRunner->run('queue.retry.failed');
    }

    /**
     * @return array<string, mixed>
     */
    public function removeFailed(): array
    {
        return $this->commandRunner->run('queue.remove.failed');
    }

    private function countByQueue(string $queueName): int
    {
        try {
            $value = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queue',
                ['queue' => $queueName],
            );

            return (int) $value;
        } catch (\Throwable) {
            return 0;
        }
    }
}
