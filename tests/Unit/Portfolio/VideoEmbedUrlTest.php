<?php

declare(strict_types=1);

namespace App\Tests\Unit\Portfolio;

use App\Module\Portfolio\Domain\ValueObject\VideoEmbedUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VideoEmbedUrlTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function links(): iterable
    {
        yield 'rutube: страница видео' => ['https://rutube.ru/video/'.self::ID.'/', VideoEmbedUrl::RUTUBE, 'https://rutube.ru/play/embed/'.self::ID.'/'];
        yield 'rutube: без протокола и www' => ['www.rutube.ru/video/'.strtoupper(self::ID), VideoEmbedUrl::RUTUBE, 'https://rutube.ru/play/embed/'.self::ID.'/'];
        yield 'rutube: уже плеер' => ['https://rutube.ru/play/embed/'.self::ID, VideoEmbedUrl::RUTUBE, 'https://rutube.ru/play/embed/'.self::ID.'/'];
        yield 'rutube: закрытое видео с ключом' => ['https://rutube.ru/video/private/'.self::ID.'/?p=AbC-1_x', VideoEmbedUrl::RUTUBE, 'https://rutube.ru/play/embed/'.self::ID.'/?p=AbC-1_x'];
        yield 'rutube: shorts' => ['https://rutube.ru/shorts/'.self::ID.'/', VideoEmbedUrl::RUTUBE, 'https://rutube.ru/play/embed/'.self::ID.'/'];
        yield 'vk: vkvideo.ru' => ['https://vkvideo.ru/video-12345_67890', VideoEmbedUrl::VK, 'https://vkvideo.ru/video_ext.php?oid=-12345&id=67890&hd=2'];
        yield 'vk: vk.com с мобильного' => ['https://m.vk.com/video-1_2', VideoEmbedUrl::VK, 'https://vkvideo.ru/video_ext.php?oid=-1&id=2&hd=2'];
        yield 'vk: видео пользователя' => ['https://vk.com/video777_888', VideoEmbedUrl::VK, 'https://vkvideo.ru/video_ext.php?oid=777&id=888&hd=2'];
        yield 'vk: открыто поверх ленты (?z=)' => ['https://vk.com/zaborprofil?z=video-1_2%2Fpl_wall', VideoEmbedUrl::VK, 'https://vkvideo.ru/video_ext.php?oid=-1&id=2&hd=2'];
        yield 'vk: клип' => ['https://vk.com/clip-5_6', VideoEmbedUrl::VK, 'https://vkvideo.ru/video_ext.php?oid=-5&id=6&hd=2'];
        yield 'vk: код плеера с hash' => ['https://vk.com/video_ext.php?oid=-1&id=2&hash=ab12cd', VideoEmbedUrl::VK, 'https://vkvideo.ru/video_ext.php?oid=-1&id=2&hash=ab12cd&hd=2'];
    }

    #[DataProvider('links')]
    public function testTurnsWatchLinksIntoPlayerUrls(string $input, string $provider, string $embed): void
    {
        $url = VideoEmbedUrl::fromUserInput($input);

        self::assertSame($provider, $url->provider);
        self::assertSame($embed, $url->embedUrl);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreign(): iterable
    {
        yield 'youtube' => ['https://www.youtube.com/watch?v=abc'];
        yield 'похожий домен' => ['https://notrutube.ru/video/'.self::ID.'/'];
        yield 'rutube без id' => ['https://rutube.ru/channel/123/'];
        yield 'vk не видео' => ['https://vk.com/zaborprofil'];
        yield 'vk код плеера без id' => ['https://vkvideo.ru/video_ext.php?oid=-1'];
        yield 'javascript' => ['javascript:alert(1)'];
    }

    #[DataProvider('foreign')]
    public function testRejectsOtherLinks(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        VideoEmbedUrl::fromUserInput($input);
    }
}
