<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Enum;

/**
 * Вид медиа в работе портфолио: фото из медиатеки, загруженное видео (MP4/WebM) или ссылка на видео VK/Rutube.
 */
enum PortfolioMediaKind: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Embed = 'embed';
}
