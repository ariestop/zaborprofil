<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Module\Content\Application\Service\PagePublisher;
use App\Module\Content\Application\Service\PageRevisionComparison;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Entity\PagePublication;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Пакетная проверка «есть неопубликованные правки» для списка страниц совпадает с поштучной.
 */
final class PageRevisionComparisonBatchTest extends KernelTestCase
{
    public function testBatchResultMatchesPerPageComparison(): void
    {
        self::bootKernel();
        $em = $this->entityManager();
        SchemaTestHelper::recreateSchema($em);

        $unchanged = $this->publishedPage('unchanged');
        $blockChanged = $this->publishedPage('block-changed');
        $titleChanged = $this->publishedPage('title-changed');
        $neverPublished = new Page(PageType::Landing, 'Черновик', 'draft', '/draft/', 'Черновик');
        $em->persist($neverPublished);
        $em->flush();

        $block = $em->getRepository(PageBlock::class)->findOneBy(['page' => $blockChanged->id()]);
        self::assertInstanceOf(PageBlock::class, $block);
        $block->update(BlockType::RichText, 'Текст', ['html' => '<p>Изменено</p>'], [], true, $block->visibility());
        $titleChanged->update(PageType::Landing, 'Новый заголовок', 'title-changed', '/title-changed/', 'Новый заголовок', 'default', 0, true);
        $em->flush();
        $em->clear();

        $pages = $this->service(PageRepositoryInterface::class)->findAllForAdmin();
        $comparison = $this->service(PageRevisionComparison::class);
        $errors = [];
        $batch = $comparison->unpublishedChangesFor($pages, static function (Page $page, \Throwable $exception) use (&$errors): void {
            $errors[] = $exception->getMessage();
        });

        self::assertSame([], $errors);
        foreach ($pages as $page) {
            self::assertSame($comparison->hasUnpublishedChanges($page), $batch[(string) $page->id()], $page->path());
        }
        self::assertFalse($batch[(string) $unchanged->id()]);
        self::assertTrue($batch[(string) $blockChanged->id()]);
        self::assertTrue($batch[(string) $titleChanged->id()]);
        self::assertFalse($batch[(string) $neverPublished->id()]);
    }

    private function publishedPage(string $slug): Page
    {
        $em = $this->entityManager();
        $page = new Page(PageType::Landing, 'Страница '.$slug, $slug, '/'.$slug.'/', 'Страница '.$slug);
        $em->persist($page);
        $em->persist(new PageBlock($page, BlockType::RichText, 'Текст', 0, ['html' => '<p>Исходный</p>']));
        $em->flush();

        $revision = $this->service(PagePublisher::class)->snapshot($page, null, null, 'test');
        $page->publish(null);
        $publication = new PagePublication($page);
        $publication->publish($revision);
        $em->persist($publication);
        $em->flush();

        return $page;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service(EntityManagerInterface::class);
    }
}
