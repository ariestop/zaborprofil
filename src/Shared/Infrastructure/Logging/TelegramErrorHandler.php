<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\Message\SendTelegramLogMessage;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class TelegramErrorHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct(Level::Error, true);
    }

    protected function write(LogRecord $record): void
    {
        $fingerprint = $this->fingerprint($record);
        $this->cache->get('telegram_error_log.'.$fingerprint, function (ItemInterface $item) use ($record, $fingerprint): bool {
            $item->expiresAfter(300);
            $this->messageBus->dispatch(new SendTelegramLogMessage(
                $record->level->getName(),
                $this->formatMessage($record),
                $fingerprint,
            ));

            return true;
        });
    }

    private function fingerprint(LogRecord $record): string
    {
        $exception = $record->context['exception'] ?? null;
        $source = $record->channel.'|'.$record->level->getName().'|'.$record->message;

        if ($exception instanceof \Throwable) {
            $source .= '|'.$exception::class.'|'.$exception->getFile().'|'.$exception->getLine();
        }

        return hash('sha256', $source);
    }

    private function formatMessage(LogRecord $record): string
    {
        $extra = $record->extra;
        $lines = [
            '['.$record->level->getName().'] '.$record->message,
            'channel: '.$record->channel,
        ];

        foreach (['environment', 'release', 'commit', 'request_id', 'route', 'path'] as $key) {
            if (isset($extra[$key]) && \is_scalar($extra[$key])) {
                $lines[] = $key.': '.$extra[$key];
            }
        }

        return implode("\n", $lines);
    }
}
