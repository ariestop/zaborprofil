<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

final class StructuredRichTextSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><span>';

    public function sanitize(string $html): string
    {
        $withoutScripts = preg_replace('/<\s*script\b[^>]*>(.*?)<\s*\/\s*script>/is', '', $html) ?? '';
        $withoutHandlers = preg_replace('/\bon\w+="[^"]*"/i', '', $withoutScripts) ?? '';
        $stripped = strip_tags($withoutHandlers, self::ALLOWED_TAGS);

        // Remove javascript: and data: links from href attributes.
        return preg_replace_callback(
            '/href="([^"]*)"/i',
            static function (array $matches): string {
                $href = $matches[1];
                $lower = mb_strtolower(trim($href));
                if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
                    return 'href="#"';
                }

                return \sprintf('href="%s"', $href);
            },
            $stripped,
        ) ?? '';
    }
}
