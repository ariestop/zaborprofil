<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Service;

use App\Module\Settings\Application\Service\SettingsRegistry;

/**
 * Builds the effective `<title>` of a public page.
 *
 * An explicit page `metaTitle` always wins and is used verbatim. Otherwise the
 * default template from settings (`seo.title_template`) is applied to the page
 * title; with no template configured the result is the plain page title.
 */
final readonly class SeoTitleResolver
{
    public const string SETTINGS_SCOPE = 'seo';
    public const string SETTINGS_KEY_TEMPLATE = 'title_template';
    public const string SETTINGS_KEY_SITE_NAME = 'site_name';
    public const string DEFAULT_SITE_NAME = 'ЗаборПрофиль';

    public function __construct(private SettingsRegistry $settings)
    {
    }

    public function resolve(string $title, string $h1, ?string $metaTitle): string
    {
        $metaTitle = self::clean($metaTitle);
        if ($metaTitle !== null) {
            return $metaTitle;
        }

        $template = trim($this->settings->getString(self::SETTINGS_SCOPE, self::SETTINGS_KEY_TEMPLATE));
        if ($template === '') {
            return $title;
        }

        $siteName = trim($this->settings->getString(self::SETTINGS_SCOPE, self::SETTINGS_KEY_SITE_NAME, self::DEFAULT_SITE_NAME));
        $resolved = trim(strtr($template, [
            '{title}' => $title,
            '{h1}' => $h1,
            '{site_name}' => $siteName,
        ]));

        return $resolved === '' ? $title : $resolved;
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
