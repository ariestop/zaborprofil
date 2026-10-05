<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\Domain\Service;

use App\Module\Seo\Domain\Service\UrlPathEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlPathEncoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paths(): iterable
    {
        yield 'ascii stays as is' => ['/old-page/?a=1&b=2#top', '/old-page/?a=1&b=2#top'];
        yield 'cyrillic is percent-encoded' => ['/заборы/', '/%D0%B7%D0%B0%D0%B1%D0%BE%D1%80%D1%8B/'];
        yield 'space is encoded' => ['/a b/', '/a%20b/'];
        yield 'existing escapes are upper-cased' => ['/%d0%b7/', '/%D0%B7/'];
        yield 'mixed' => ['/каталог/%D0%B7/x', '/%D0%BA%D0%B0%D1%82%D0%B0%D0%BB%D0%BE%D0%B3/%D0%B7/x'];
    }

    #[DataProvider('paths')]
    public function testEncode(string $input, string $expected): void
    {
        self::assertSame($expected, UrlPathEncoder::encode($input));
    }

    public function testEncodeIsIdempotent(): void
    {
        $once = UrlPathEncoder::encode('/заборы/из профнастила/');

        self::assertSame($once, UrlPathEncoder::encode($once));
    }
}
