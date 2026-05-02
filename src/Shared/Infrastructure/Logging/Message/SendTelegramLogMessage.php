<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\Message;

final readonly class SendTelegramLogMessage
{
    public function __construct(
        public string $level,
        public string $message,
        public string $fingerprint,
    ) {
    }
}
