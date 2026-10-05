<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Service\UrlPathEncoder;

final readonly class RedirectRuleValidator
{
    public const int MAX_SOURCE_LENGTH = 512;
    public const int MAX_TARGET_LENGTH = 1024;

    /**
     * Префиксы, которые `RedirectKernelSubscriber` не обрабатывает: правило с таким источником никогда не сработает.
     *
     * @var list<string>
     */
    public const array IGNORED_SOURCE_PREFIXES = [
        '/admin',
        '/build',
        '/health',
        '/uploads',
        '/_profiler',
        '/_wdt',
    ];

    /**
     * @var list<string>
     */
    private const array FORBIDDEN_TARGET_PREFIXES = ['/admin', '/_profiler', '/_wdt'];

    /**
     * @var list<int>
     */
    public const array ALLOWED_STATUS_CODES = [301, 302, 307, 308];

    public function __construct(private string $siteUrl)
    {
    }

    public function validate(string $source, string $target, int $statusCode): RedirectRule
    {
        $sourcePath = $this->validateSource($source);
        $targetPath = $this->validateTarget($target);

        $internalTarget = $this->internalPath($targetPath);
        if ($internalTarget !== null && $internalTarget === $sourcePath) {
            throw new RedirectValidationException('Редирект не может вести на тот же адрес, что и источник.', 'targetPath');
        }

        if (!\in_array($statusCode, self::ALLOWED_STATUS_CODES, true)) {
            throw new RedirectValidationException('Код ответа должен быть одним из: 301, 302, 307, 308.', 'statusCode');
        }

        return new RedirectRule($sourcePath, $targetPath, $statusCode);
    }

    /**
     * Путь без query/якоря, если цель находится на этом же сайте; `null` для внешних адресов.
     */
    public function internalPath(string $target): ?string
    {
        if (str_starts_with($target, '/')) {
            return self::stripQueryAndFragment($target);
        }

        $parts = parse_url($target);
        if ($parts === false || !isset($parts['host'])) {
            return null;
        }

        $siteHost = parse_url($this->siteUrl, PHP_URL_HOST);
        if (!\is_string($siteHost) || $siteHost === '' || strcasecmp($siteHost, $parts['host']) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        return $path === '' ? '/' : $path;
    }

    private function validateSource(string $source): string
    {
        $trimmed = trim($source);
        if ($trimmed === '') {
            throw new RedirectValidationException('Укажите исходный URL.', 'sourcePath');
        }

        if (preg_match('/[?#]/', $trimmed) === 1) {
            throw new RedirectValidationException('Исходный URL не должен содержать параметры запроса и якорь (? и #): сервер сопоставляет только путь.', 'sourcePath');
        }

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:\/\//', $trimmed) === 1) {
            throw new RedirectValidationException('Исходный URL должен быть путём без домена, например /old-page/.', 'sourcePath');
        }

        if (str_contains($trimmed, '//') || preg_match('/[\x00-\x1F\x7F\\\\]/', $trimmed) === 1) {
            throw new RedirectValidationException('Исходный URL содержит недопустимые символы или двойной слэш.', 'sourcePath');
        }

        $path = Redirect::normalizeSourcePath($trimmed);
        if (\strlen($path) > self::MAX_SOURCE_LENGTH) {
            throw new RedirectValidationException(\sprintf('Исходный URL длиннее %d символов.', self::MAX_SOURCE_LENGTH), 'sourcePath');
        }

        foreach (self::IGNORED_SOURCE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                throw new RedirectValidationException(\sprintf('Адреса, начинающиеся с %s, обрабатываются приложением напрямую и не могут быть источником редиректа.', $prefix), 'sourcePath');
            }
        }

        return $path;
    }

    private function validateTarget(string $target): string
    {
        $trimmed = trim($target);
        if ($trimmed === '') {
            throw new RedirectValidationException('Укажите целевой URL.', 'targetPath');
        }

        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $trimmed) === 1) {
            throw new RedirectValidationException('Целевой URL содержит недопустимые символы.', 'targetPath');
        }

        if (str_starts_with($trimmed, '//')) {
            throw new RedirectValidationException('Целевой URL не может начинаться с // (протокол-относительная ссылка). Укажите путь от корня или полный адрес с https://.', 'targetPath');
        }

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:/', $trimmed) === 1) {
            if (preg_match('/^https?:\/\/[^\/?#\s]+/i', $trimmed) !== 1) {
                throw new RedirectValidationException('Для внешнего адреса допустимы только http:// и https:// со ссылкой на домен.', 'targetPath');
            }
        }

        $normalized = Redirect::normalizeTargetPath($trimmed);
        if (\strlen($normalized) > self::MAX_TARGET_LENGTH) {
            throw new RedirectValidationException(\sprintf('Целевой URL длиннее %d символов.', self::MAX_TARGET_LENGTH), 'targetPath');
        }

        $internal = $this->internalPath($normalized);
        if ($internal !== null) {
            foreach (self::FORBIDDEN_TARGET_PREFIXES as $prefix) {
                if ($internal === $prefix || str_starts_with($internal, $prefix.'/')) {
                    throw new RedirectValidationException(\sprintf('Редирект на служебный раздел %s запрещён.', $prefix), 'targetPath');
                }
            }
        }

        return $normalized;
    }

    private static function stripQueryAndFragment(string $path): string
    {
        $cut = strcspn($path, '?#');

        return UrlPathEncoder::encode(substr($path, 0, $cut));
    }
}
