<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\NotFound;

use App\Module\Seo\Application\Redirect\RedirectRuleValidator;
use App\Module\Seo\Domain\Repository\NotFoundLogRepositoryInterface;
use App\Module\Seo\Domain\Service\UrlPathEncoder;
use DateTimeImmutable;

/**
 * Фиксирует публичные 404 в агрегированном журнале. Таблица ограничена по размеру, чтобы
 * сканеры уязвимостей не могли раздуть её случайными путями.
 */
final readonly class NotFoundRecorder
{
    public const int MAX_ENTRIES = 5000;
    public const int MAX_PATH_LENGTH = 512;
    public const int MAX_REFERRER_LENGTH = 512;

    public function __construct(private NotFoundLogRepositoryInterface $log)
    {
    }

    public function record(string $path, ?string $referrer): void
    {
        $normalized = UrlPathEncoder::encode($path);
        if (!str_starts_with($normalized, '/') || \strlen($normalized) > self::MAX_PATH_LENGTH || $this->isIgnored($normalized)) {
            return;
        }

        $this->log->registerHit($normalized, self::sanitizeReferrer($referrer), new DateTimeImmutable(), self::MAX_ENTRIES);
    }

    private function isIgnored(string $path): bool
    {
        return array_any(RedirectRuleValidator::IGNORED_SOURCE_PREFIXES, static fn (string $prefix): bool => str_starts_with($path, $prefix));
    }

    /**
     * Query и якорь отбрасываются: в них бывают токены и персональные данные.
     */
    private static function sanitizeReferrer(?string $referrer): ?string
    {
        if ($referrer === null || $referrer === '') {
            return null;
        }

        $parts = parse_url($referrer);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || !\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $clean = strtolower($parts['scheme']).'://'.$parts['host'].($parts['path'] ?? '');

        return substr(UrlPathEncoder::encode($clean), 0, self::MAX_REFERRER_LENGTH);
    }
}
