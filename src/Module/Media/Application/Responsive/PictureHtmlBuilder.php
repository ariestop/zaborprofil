<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Responsive;

/**
 * Собирает разметку `<picture>` (или обычный `<img>`, если картинка не из медиатеки).
 *
 * Опции: `alt`, `fallback_alt`, `decorative` (пустой alt), `class`, `sizes`, `priority` (первый экран: eager + fetchpriority=high),
 * `eager` (eager без fetchpriority).
 */
final readonly class PictureHtmlBuilder
{
    public const string DEFAULT_SIZES = '(min-width: 1152px) 1152px, 100vw';

    /**
     * @param array<string, mixed> $options
     */
    public function build(string $src, ?ResponsiveImage $image, array $options = []): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }

        $priority = ($options['priority'] ?? false) === true;
        $eager = $priority || ($options['eager'] ?? false) === true;
        $alt = ($options['decorative'] ?? false) === true ? '' : $this->firstText($options['alt'] ?? null, $image?->alt, $options['fallback_alt'] ?? null) ?? '';
        $class = $this->text($options['class'] ?? null);
        $sizes = $this->text($options['sizes'] ?? null) ?? self::DEFAULT_SIZES;

        $attributes = [
            'src' => $image instanceof ResponsiveImage ? $image->src : $src,
            'alt' => $alt,
        ];
        if ($image instanceof ResponsiveImage && $image->width !== null && $image->height !== null) {
            $attributes['width'] = (string) $image->width;
            $attributes['height'] = (string) $image->height;
        }
        if ($class !== null) {
            $attributes['class'] = $class;
        }
        $attributes['loading'] = $eager ? 'eager' : 'lazy';
        $attributes['decoding'] = 'async';
        if ($priority) {
            $attributes['fetchpriority'] = 'high';
        }
        if ($image instanceof ResponsiveImage && $image->focalX !== null && $image->focalY !== null) {
            $attributes['style'] = \sprintf('object-position: %d%% %d%%', $image->focalX, $image->focalY);
        }

        $img = '<img'.$this->attributes($attributes).'>';
        if (!$image instanceof ResponsiveImage || $image->sources === []) {
            return $img;
        }

        $sources = '';
        foreach ($image->sources as $source) {
            $sources .= '<source'.$this->attributes([
                'type' => $source->mimeType,
                'srcset' => $source->srcset(),
                'sizes' => $sizes,
            ]).'>';
        }

        return '<picture>'.$sources.$img.'</picture>';
    }

    /**
     * @param array<string, string> $attributes
     */
    private function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            $html .= \sprintf(' %s="%s"', $name, htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        return $html;
    }

    private function firstText(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $text = $this->text($value);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    private function text(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
