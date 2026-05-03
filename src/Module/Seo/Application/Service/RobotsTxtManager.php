<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Service;

use App\Module\Settings\Application\Service\SettingsRegistry;
use InvalidArgumentException;

final readonly class RobotsTxtManager
{
    public const string SCOPE = 'seo';
    public const string KEY = 'robots_txt';

    public function __construct(
        private SettingsRegistry $settings,
        private string $environment,
        private string $siteUrl,
    ) {
    }

    public function body(): string
    {
        if ($this->environment !== 'prod') {
            return "User-agent: *\nDisallow: /\n";
        }

        $custom = $this->editableBody();
        if (trim($custom) !== '') {
            return $this->withTrailingNewline($custom);
        }

        return $this->defaultProductionBody();
    }

    public function editableBody(): string
    {
        return $this->settings->getString(self::SCOPE, self::KEY);
    }

    public function normalizeEditableBody(?string $body): ?string
    {
        if ($body === null || trim($body) === '') {
            return null;
        }

        if (str_contains($body, "\0")) {
            throw new InvalidArgumentException('robots.txt cannot contain NUL bytes.');
        }

        if (mb_strlen($body) > 10000) {
            throw new InvalidArgumentException('robots.txt must be at most 10000 characters.');
        }

        return $this->withTrailingNewline($body);
    }

    private function defaultProductionBody(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /api/',
        ];

        if (trim($this->siteUrl) !== '') {
            $lines[] = '';
            $lines[] = 'Sitemap: '.rtrim(trim($this->siteUrl), '/').'/sitemap.xml';
        }

        return implode("\n", $lines)."\n";
    }

    private function withTrailingNewline(string $body): string
    {
        return rtrim(str_replace(["\r\n", "\r"], "\n", $body))."\n";
    }
}
