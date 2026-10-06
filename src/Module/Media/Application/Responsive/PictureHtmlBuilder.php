<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Responsive;

/**
 * Собирает разметку `<picture>` (или обычный `<img>`, если картинка не из медиатеки).
 *
 * Опции: `alt`, `fallback_alt`, `decorative` (пустой alt), `class`, `sizes`, `priority` (первый экран: eager + fetchpriority=high),
 * `eager` (eager без fetchpriority).
 *
 * Экраны с плотностью от 2,5 (DPR 3) получают картинки как для DPR 2: в `sizes` для них слот уже в 2/3.
 * На телефоне разница с DPR 3 на глаз почти не видна, а вес картинок заметно меньше.
 */
final readonly class PictureHtmlBuilder
{
    public const string DEFAULT_SIZES = '(min-width: 1152px) 1152px, 100vw';

    private const string HIGH_DENSITY_QUERY = '(min-resolution: 2.5dppx)';

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

        $sizes = $this->capHighDensity($sizes);
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
     * Добавляет перед исходными правилами `sizes` их копии для плотных экранов со слотом 2/3:
     * `(min-width: 768px) 50vw, 100vw` → `(min-resolution: 2.5dppx) and (min-width: 768px) calc(50vw * 2 / 3), …, (min-width: 768px) 50vw, 100vw`.
     */
    private function capHighDensity(string $sizes): string
    {
        $capped = [];
        foreach ($this->splitTopLevel($sizes) as $entry) {
            [$condition, $length] = $this->splitSizeEntry($entry);
            if ($length === '' || strtolower($length) === 'auto') {
                return $sizes;
            }

            $media = $condition === '' ? self::HIGH_DENSITY_QUERY : self::HIGH_DENSITY_QUERY.' and '.$this->mediaGroup($condition);
            $capped[] = $media.' calc('.$length.' * 2 / 3)';
        }

        return $capped === [] ? $sizes : implode(', ', $capped).', '.$sizes;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        foreach (str_split($value) as $char) {
            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }
            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            }
            $current .= $char;
        }
        $parts[] = $current;

        return array_values(array_filter(array_map(trim(...), $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * Делит правило `sizes` на условие и длину: `(min-width: 768px) calc(50vw - 16px)` → [`(min-width: 768px)`, `calc(50vw - 16px)`].
     *
     * @return array{string, string}
     */
    private function splitSizeEntry(string $entry): array
    {
        $start = strrpos($entry, ' ');
        if (str_ends_with($entry, ')')) {
            $open = $this->matchingOpenParen($entry, \strlen($entry) - 1);
            $start = $open;
            while ($start > 0 && preg_match('/[a-z-]/i', $entry[$start - 1]) === 1) {
                --$start;
            }
        } elseif ($start !== false) {
            ++$start;
        }
        $start = $start === false ? 0 : $start;

        return [trim(substr($entry, 0, $start)), trim(substr($entry, $start))];
    }

    private function mediaGroup(string $condition): string
    {
        $isSingleGroup = str_starts_with($condition, '(') && str_ends_with($condition, ')') && $this->matchingOpenParen($condition, \strlen($condition) - 1) === 0;

        return $isSingleGroup ? $condition : '('.$condition.')';
    }

    private function matchingOpenParen(string $value, int $close): int
    {
        $depth = 0;
        for ($i = $close; $i >= 0; --$i) {
            if ($value[$i] === ')') {
                ++$depth;
            } elseif ($value[$i] === '(' && --$depth === 0) {
                return $i;
            }
        }

        return 0;
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
