<?php

declare(strict_types=1);

namespace App\Shared\Application\Logging;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class BusinessEventLogger
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.business')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $event, array $context = []): void
    {
        $this->logger->info($event, [
            'event' => $event,
            ...$context,
        ]);
    }
}
