<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Ссылка на видео VK или Rutube, приведённая к адресу встраиваемого плеера.
 * Редактор вставляет обычную ссылку со страницы видео — сайт сам строит адрес для iframe:
 *   rutube.ru/video/<id>/            → https://rutube.ru/play/embed/<id>/
 *   rutube.ru/video/private/<id>/?p= → https://rutube.ru/play/embed/<id>/?p=…
 *   vk.com|vkvideo.ru/video-1_2      → https://vkvideo.ru/video_ext.php?oid=-1&id=2&hd=2
 *   …/video_ext.php?oid=…&id=…       → без изменений (хост приводится к vkvideo.ru)
 */
final readonly class VideoEmbedUrl
{
    public const string RUTUBE = 'rutube';
    public const string VK = 'vk';

    private function __construct(public string $provider, public string $embedUrl)
    {
    }

    public static function fromUserInput(string $input): self
    {
        $url = trim($input);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = (string) preg_replace('/^(www\.|m\.)/', '', $host);
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        if ($host === 'rutube.ru') {
            if (preg_match('#^/(?:play/embed|video(?:/private)?|shorts)/([0-9a-f]{32})/?$#i', $path, $m)) {
                $key = \is_string($query['p'] ?? null) && preg_match('/^[\w-]+$/', $query['p']) ? '?p='.$query['p'] : '';

                return new self(self::RUTUBE, 'https://rutube.ru/play/embed/'.strtolower($m[1]).'/'.$key);
            }
        }

        if (\in_array($host, ['vk.com', 'vkvideo.ru', 'vk.ru'], true)) {
            $owner = $query['oid'] ?? null;
            $video = $query['id'] ?? null;
            if ($path === '/video_ext.php' && \is_string($owner) && \is_string($video) && self::isVkId($owner) && self::isVkId($video, false)) {
                return self::vk($owner, $video, $query['hash'] ?? null);
            }
            $target = $path;
            if (\is_string($query['z'] ?? null)) {
                $target = '/'.explode('/', $query['z'])[0];
            }
            if (preg_match('#^/(?:video|clip)(-?\d+)_(\d+)#', $target, $m)) {
                return self::vk($m[1], $m[2], $query['hash'] ?? null);
            }
        }

        throw new InvalidArgumentException('Ссылка должна вести на видео VK или Rutube, например https://rutube.ru/video/… или https://vkvideo.ru/video-123_456.');
    }

    private static function vk(string $owner, string $id, mixed $hash): self
    {
        $url = 'https://vkvideo.ru/video_ext.php?oid='.$owner.'&id='.$id;
        if (\is_string($hash) && preg_match('/^[0-9a-f]+$/i', $hash)) {
            $url .= '&hash='.$hash;
        }

        return new self(self::VK, $url.'&hd=2');
    }

    private static function isVkId(string $value, bool $signed = true): bool
    {
        return preg_match($signed ? '/^-?\d+$/' : '/^\d+$/', $value) === 1;
    }
}
