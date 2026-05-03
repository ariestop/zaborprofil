<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Application\Service;

use App\Module\Content\Application\Service\PublicPageView;
use App\Module\Menu\Application\Service\BreadcrumbBuilder;
use PHPUnit\Framework\TestCase;

final class BreadcrumbBuilderTest extends TestCase
{
    public function testBuildsBreadcrumbsFromPagePath(): void
    {
        $page = new PublicPageView(
            '01HX',
            'Металлические заборы',
            'Металлические заборы',
            '/catalog/metallicheskie-zabory/',
            'landing',
            true,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            [],
        );

        $breadcrumbs = (new BreadcrumbBuilder())->forPage($page);

        self::assertCount(3, $breadcrumbs);
        self::assertSame('Главная', $breadcrumbs[0]->label);
        self::assertSame('/catalog/', $breadcrumbs[1]->path);
        self::assertSame('Catalog', $breadcrumbs[1]->label);
        self::assertSame('Металлические заборы', $breadcrumbs[2]->label);
        self::assertTrue($breadcrumbs[2]->current);
    }
}
