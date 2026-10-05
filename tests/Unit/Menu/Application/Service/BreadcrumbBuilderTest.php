<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Application\Service;

use App\Module\Content\Application\Service\PublicPageView;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
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

    public function testSkipsIntermediateSectionsWithoutPublishedPage(): void
    {
        $repository = $this->createStub(PageRepositoryInterface::class);
        $repository->method('findPublishedByPath')->willReturn(null);

        $breadcrumbs = (new BreadcrumbBuilder($repository))->forPage($this->page('/fasad/profnastil/', 'Профнастил'));

        self::assertCount(2, $breadcrumbs);
        self::assertSame('Главная', $breadcrumbs[0]->label);
        self::assertSame('Профнастил', $breadcrumbs[1]->label);
        self::assertTrue($breadcrumbs[1]->current);
    }

    public function testUsesPublishedParentPageTitleAndLink(): void
    {
        $parent = new Page(PageType::Category, 'Фасадные материалы', 'fasad', '/fasad/', 'Фасадные материалы');
        $repository = $this->createStub(PageRepositoryInterface::class);
        $repository->method('findPublishedByPath')->willReturnCallback(static fn (string $path): ?Page => $path === '/fasad/' ? $parent : null);

        $breadcrumbs = (new BreadcrumbBuilder($repository))->forPage($this->page('/fasad/profnastil/', 'Профнастил'));

        self::assertCount(3, $breadcrumbs);
        self::assertSame('Фасадные материалы', $breadcrumbs[1]->label);
        self::assertSame('/fasad/', $breadcrumbs[1]->path);
    }

    private function page(string $path, string $h1): PublicPageView
    {
        return new PublicPageView('01HX', $h1, $h1, $path, 'landing', true, null, null, null, null, null, null, null, []);
    }
}
