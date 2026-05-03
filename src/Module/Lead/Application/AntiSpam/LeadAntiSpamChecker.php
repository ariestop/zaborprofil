<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\AntiSpam;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class LeadAntiSpamChecker
{
    private const int MIN_FILL_SECONDS = 3;
    private const int MAX_REQUESTS_PER_HOUR = 5;

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function check(array $payload, Request $request): LeadSpamCheckResult
    {
        $score = 0;
        $reasons = [];

        if (($payload['website'] ?? '') !== '') {
            $score += 100;
            $reasons[] = 'honeypot_filled';
        }

        $loadedAt = $payload['formLoadedAt'] ?? null;
        if (\is_string($loadedAt)) {
            $loadedAtTimestamp = strtotime($loadedAt);
            if ($loadedAtTimestamp !== false && time() - $loadedAtTimestamp < self::MIN_FILL_SECONDS) {
                $score += 60;
                $reasons[] = 'submitted_too_fast';
            }
        }

        $requestCount = $this->incrementRequestCount($request);
        if ($requestCount > self::MAX_REQUESTS_PER_HOUR) {
            $score += 80;
            $reasons[] = 'rate_limit_exceeded';
        }

        $messageValue = $payload['message'] ?? '';
        $message = \is_string($messageValue) ? $messageValue : '';
        if (preg_match_all('#https?://#i', $message) > 2) {
            $score += 50;
            $reasons[] = 'too_many_links';
        }

        return new LeadSpamCheckResult($score, $reasons);
    }

    private function incrementRequestCount(Request $request): int
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $key = 'lead.spam.ip.'.hash('xxh128', $ip);
        $item = $this->cache->getItem($key);
        $count = $item->isHit() && \is_int($item->get()) ? $item->get() : 0;
        ++$count;
        $item->set($count);
        $item->expiresAfter(3600);
        $this->cache->save($item);

        return $count;
    }
}
