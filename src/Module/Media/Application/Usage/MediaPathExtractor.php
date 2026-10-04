<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Usage;

final class MediaPathExtractor
{
    public const string PREFIX = '/uploads/media/';

    private const string PATTERN = '#/uploads/media/[A-Za-z0-9._~%\-]+(?:/[A-Za-z0-9._~%\-]+)*#';

    /**
     * Находит в произвольном тексте (JSON, HTML, URL) все пути файлов медиатеки.
     *
     * @return list<string>
     */
    public static function extract(?string $text): array
    {
        if ($text === null || !str_contains($text, 'uploads')) {
            return [];
        }

        $normalized = str_replace('\\/', '/', $text);
        if (preg_match_all(self::PATTERN, $normalized, $matches) < 1) {
            return [];
        }

        return array_values(array_unique($matches[0]));
    }
}
