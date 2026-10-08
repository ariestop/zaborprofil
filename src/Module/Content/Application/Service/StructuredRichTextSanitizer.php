<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Очистка текстовых полей блоков перед сохранением.
 *
 * - {@see sanitizeHtml()} — для HTML, который выводится как разметка (блок rich-text): санитайзер
 *   `app.rich_text_sanitizer` из config/packages/html_sanitizer.yaml. Тот же санитайзер повторно
 *   применяется при выводе в шаблоне, поэтому ранее сохранённый HTML тоже безопасен.
 * - {@see sanitizeText()} — для полей, которые шаблоны выводят с экранированием: прежняя очистка тегов.
 *   Она не отвечает за безопасность (её даёт экранирование Twig) и сохранена, чтобы не менять содержимое
 *   таких полей (HtmlSanitizer закодировал бы `&` и кавычки, и они показались бы как `&amp;`).
 */
final readonly class StructuredRichTextSanitizer
{
    private const string ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><span>';

    public function __construct(
        #[Target('app.rich_text_sanitizer')]
        private HtmlSanitizerInterface $htmlSanitizer,
    ) {
    }

    public function sanitizeHtml(string $html): string
    {
        return $this->htmlSanitizer->sanitize($html);
    }

    public function sanitizeText(string $text): string
    {
        $withoutScripts = preg_replace('/<\s*script\b[^>]*>(.*?)<\s*\/\s*script>/is', '', $text) ?? '';
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
