<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\Processor;

use Monolog\LogRecord;

final readonly class PiiRedactorProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = PiiMasker::maskValue($record->context);
        $extra = PiiMasker::maskValue($record->extra);

        return $record->with(
            message: PiiMasker::maskString($record->message),
            context: \is_array($context) ? $context : [],
            extra: \is_array($extra) ? $extra : [],
        );
    }
}
