<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\Processor;

use Monolog\LogRecord;
use Throwable;

final readonly class PiiRedactorProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactString($record->message),
            context: $this->redactValue($record->context),
            extra: $this->redactValue($record->extra),
        );
    }

    private function redactValue(mixed $value): mixed
    {
        if (\is_string($value)) {
            return $this->redactString($value);
        }

        if ($value instanceof Throwable) {
            return $value;
        }

        if (!\is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $redacted[$key] = $this->isSensitiveKey($key) ? '[redacted]' : $this->redactValue($item);
        }

        return $redacted;
    }

    private function isSensitiveKey(mixed $key): bool
    {
        if (!\is_string($key)) {
            return false;
        }

        return preg_match('/(password|passwd|secret|token|authorization|cookie|phone|email)/i', $key) === 1;
    }

    private function redactString(string $value): string
    {
        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $value) ?? $value;
        $value = preg_replace('/(?:\+7|8)\s?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/', '[phone]', $value) ?? $value;

        return preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip]', $value) ?? $value;
    }
}
