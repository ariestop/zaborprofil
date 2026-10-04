<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Тестовая схема строится SchemaTool из ожидаемой схемы Doctrine, куда generated column
 * `path_active` и unique-индекс добавляет PagePathActiveSchemaListener — как и миграции.
 */
final class PagePathActiveConstraintTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        SchemaTestHelper::recreateSchema($this->entityManager());
    }

    public function testDatabaseRejectsTwoLivePagesWithSamePath(): void
    {
        $pages = $this->pageRepository();
        $pages->save(new Page(PageType::Landing, 'Первая', 'first', '/same/', 'Первая'));

        $this->expectException(UniqueConstraintViolationException::class);

        $pages->save(new Page(PageType::Landing, 'Вторая', 'second', '/same/', 'Вторая'));
    }

    public function testDatabaseAllowsReusingPathOfSoftDeletedPage(): void
    {
        $pages = $this->pageRepository();

        $old = new Page(PageType::Landing, 'Старая', 'old', '/reuse/', 'Старая');
        $pages->save($old);
        $old->delete();
        $pages->save($old);

        $pages->save(new Page(PageType::Landing, 'Новая', 'new', '/reuse/', 'Новая'));

        self::assertTrue($pages->existsByPath('/reuse/'));
    }

    public function testGeneratedColumnIsNullForSoftDeletedPage(): void
    {
        $pages = $this->pageRepository();
        $page = new Page(PageType::Landing, 'Страница', 'page', '/generated/', 'Страница');
        $pages->save($page);

        self::assertSame('/generated/', $this->pathActive('/generated/'));

        $page->delete();
        $pages->save($page);

        self::assertNull($this->pathActive('/generated/'));
    }

    private function pathActive(string $path): ?string
    {
        $value = $this->entityManager()->getConnection()->fetchOne(
            'SELECT path_active FROM content_pages WHERE path = :path',
            ['path' => $path],
        );

        return \is_string($value) ? $value : null;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }

    private function pageRepository(): PageRepositoryInterface
    {
        $repository = self::getContainer()->get(PageRepositoryInterface::class);

        if (!$repository instanceof PageRepositoryInterface) {
            throw new LogicException('Page repository service is not available.');
        }

        return $repository;
    }
}
