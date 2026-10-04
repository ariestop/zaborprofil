<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\ClientError;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Клиентские ошибки SPA админки попадают в канал `observability`: оттуда они уходят в Telegram (error+) и в Sentry.
 */
final readonly class ClientErrorReporter
{
    private const int MESSAGE_PREFIX_LENGTH = 120;

    public function __construct(
        #[Autowire(service: 'monolog.logger.observability')]
        private LoggerInterface $logger,
    ) {
    }

    public function report(ClientErrorReport $report, ?string $actorId): void
    {
        $this->logger->error('admin.client.error: '.mb_substr($report->message, 0, self::MESSAGE_PREFIX_LENGTH), [
            'event' => 'admin.client.error',
            'source' => $report->source,
            'clientMessage' => $report->message,
            'path' => $report->path,
            'stack' => $report->stack,
            'componentStack' => $report->componentStack,
            'actorId' => $actorId,
        ]);
    }
}
