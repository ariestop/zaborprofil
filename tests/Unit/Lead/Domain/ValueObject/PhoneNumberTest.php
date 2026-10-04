<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lead\Domain\ValueObject;

use App\Module\Lead\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    public function testDigitsNormalizeRussianPrefix(): void
    {
        self::assertSame('79001234567', PhoneNumber::digits('8 (900) 123-45-67'));
        self::assertSame('79001234567', PhoneNumber::digits('+7 900 123 45 67'));
        self::assertSame('8900', PhoneNumber::digits('8 900'));
    }

    public function testMaskKeepsOnlyFirstAndLastTwoDigits(): void
    {
        $masked = PhoneNumber::mask('+7 (900) 123-45-67');

        self::assertSame('7********67', $masked);
        self::assertStringNotContainsString('900', $masked);
        self::assertSame('***', PhoneNumber::mask('12'));
    }
}
