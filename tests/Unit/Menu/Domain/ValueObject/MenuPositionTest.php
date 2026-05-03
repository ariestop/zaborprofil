<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Domain\ValueObject;

use App\Module\Menu\Domain\ValueObject\MenuPosition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MenuPositionTest extends TestCase
{
    public function testNormalizesSupportedPosition(): void
    {
        self::assertSame(MenuPosition::HEADER, MenuPosition::normalize(' Header '));
    }

    public function testRejectsUnsupportedPosition(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MenuPosition::normalize('sidebar');
    }
}
