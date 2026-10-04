<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use App\Shared\Infrastructure\Logging\Processor\PiiMasker;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\UserDataBag;

/**
 * Убирает из событий Sentry всё, что может содержать персональные данные: cookies, заголовки,
 * тело и query-строку запроса, IP и email пользователя, breadcrumbs. Остаются маршрут, метод и id пользователя.
 */
final readonly class SentryEventScrubber
{
    public function __invoke(Event $event, ?EventHint $hint = null): Event
    {
        $event->setRequest($this->scrubRequest($event->getRequest()));
        $event->setBreadcrumb([]);
        $event->setUser($this->scrubUser($event->getUser()));
        $event->setServerName(null);

        $message = $event->getMessage();
        if ($message !== null) {
            $event->setMessage(PiiMasker::maskString($message));
        }

        $extra = PiiMasker::maskValue($event->getExtra());
        $event->setExtra(\is_array($extra) ? $this->stringKeys($extra) : []);

        $exceptions = [];
        foreach ($event->getExceptions() as $exception) {
            $exceptions[] = $this->scrubException($exception);
        }
        $event->setExceptions($exceptions);

        return $event;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function scrubRequest(array $request): array
    {
        $scrubbed = [];

        $method = $request['method'] ?? null;
        if (\is_string($method)) {
            $scrubbed['method'] = $method;
        }

        $url = $request['url'] ?? null;
        if (\is_string($url)) {
            $scrubbed['url'] = explode('?', $url, 2)[0];
        }

        return $scrubbed;
    }

    private function scrubUser(?UserDataBag $user): ?UserDataBag
    {
        $id = $user?->getId();
        if ($id === null) {
            return null;
        }

        return UserDataBag::createFromUserIdentifier((string) $id);
    }

    private function scrubException(ExceptionDataBag $exception): ExceptionDataBag
    {
        $exception->setValue(PiiMasker::maskString($exception->getValue()));

        return $exception;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     */
    private function stringKeys(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
