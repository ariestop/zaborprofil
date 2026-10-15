<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\ValueObject;

use App\Module\Portfolio\Domain\Enum\PortfolioMediaKind;
use InvalidArgumentException;

/**
 * Проверенное медиа перед записью в работу: фото и видео — только из медиатеки, VK/Rutube — адрес плеера.
 */
final readonly class PortfolioMediaDraft
{
    private const string IMAGE = '#^/uploads/media/[\w./-]+\.(jpe?g|png|webp|avif)$#i';
    private const string VIDEO = '#^/uploads/media/[\w./-]+\.(mp4|webm)$#i';

    public string $src;
    public ?string $poster;
    public ?string $caption;

    public function __construct(public PortfolioMediaKind $kind, string $src, ?string $poster = null, ?string $caption = null, public bool $cover = false)
    {
        $src = trim($src);
        $this->src = match ($kind) {
            PortfolioMediaKind::Photo => self::matching($src, self::IMAGE, 'Фото выбирается из медиатеки (JPG, PNG, WebP или AVIF).'),
            PortfolioMediaKind::Video => self::matching($src, self::VIDEO, 'Видео загружается в медиатеку в формате MP4 или WebM.'),
            PortfolioMediaKind::Embed => VideoEmbedUrl::fromUserInput($src)->embedUrl,
        };
        $poster = trim((string) $poster);
        $this->poster = $kind === PortfolioMediaKind::Photo || $poster === '' ? null : self::matching($poster, self::IMAGE, 'Обложка видео — фото из медиатеки.');
        $caption = trim((string) $caption);
        if (mb_strlen($caption) > 255) {
            throw new InvalidArgumentException('Подпись к фото или видео — не длиннее 255 символов.');
        }
        $this->caption = $caption === '' ? null : $caption;
        if ($cover && $kind !== PortfolioMediaKind::Photo) {
            throw new InvalidArgumentException('Главным может быть только фото.');
        }
    }

    private static function matching(string $value, string $pattern, string $message): string
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
