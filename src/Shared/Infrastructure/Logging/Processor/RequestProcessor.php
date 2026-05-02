<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\Processor;

use App\Shared\Infrastructure\Http\RequestIdSubscriber;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class RequestProcessor
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return $record;
        }

        $record->extra['request_id'] = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE);
        $record->extra['route'] = $request->attributes->get('_route');
        $record->extra['method'] = $request->getMethod();
        $record->extra['path'] = $request->getPathInfo();
        $record->extra['ip'] = $request->getClientIp();
        $record->extra['user_agent'] = $request->headers->get('User-Agent');
        $record->extra['duration_ms'] = $this->durationMilliseconds($request);
        $record->extra['memory_usage'] = memory_get_usage(true);

        return $record;
    }

    private function durationMilliseconds(Request $request): ?float
    {
        $startedAt = $request->attributes->get(RequestIdSubscriber::START_TIME_ATTRIBUTE);
        if (!\is_float($startedAt)) {
            return null;
        }

        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
