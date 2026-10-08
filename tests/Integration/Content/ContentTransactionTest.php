<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Module\Content\Application\Command\RollbackPageRevisionCommand;
use App\Module\Content\Application\Handler\RollbackPageRevisionHandler;
use App\Module\Content\Application\Service\PagePublisher;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use App\Shared\Application\Transaction\TransactionRunnerInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;
use ValueError;

/**
 * Многошаговые операции Content выполняются одной транзакцией (docs/17-doctrine-and-database.md §7.1).
 */
final class ContentTransactionTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        SchemaTestHelper::recreateSchema($this->entityManager());
    }

    /**
     * Снимок ревизии со вторым блоком неизвестного типа: раньше откат успевал удалить блоки страницы
     * и сохранить первый блок снимка до ошибки — страница оставалась с частью контента.
     */
    public function testFailedRollbackLeavesPageAndBlocksUntouched(): void
    {
        $page = new Page(PageType::Landing, 'Заборы', 'fences', '/fences/', 'Заборы');
        $this->service(PageRepositoryInterface::class)->save($page);
        $blocks = $this->service(PageBlockRepositoryInterface::class);
        $blocks->save(new PageBlock($page, BlockType::RichText, 'Первый', 0, ['html' => '<p>Один</p>']));
        $blocks->save(new PageBlock($page, BlockType::RichText, 'Второй', 1, ['html' => '<p>Два</p>']));

        $revision = $this->service(PagePublisher::class)->snapshot($page, null, null, 'manual');
        $pageId = (string) $page->id();
        $revisionId = (string) $revision->id();

        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE content_page_revisions SET title = :title, blocks_snapshot = :blocks WHERE id = :id',
            [
                'title' => 'Старый заголовок',
                'blocks' => json_encode([
                    ['type' => 'rich-text', 'name' => 'Из ревизии', 'position' => 0, 'content' => ['html' => '<p>Ревизия</p>']],
                    ['type' => 'no-such-block', 'name' => 'Битый', 'position' => 1, 'content' => []],
                ], JSON_THROW_ON_ERROR),
                'id' => $revision->id()->toBinary(),
            ],
        );
        $this->entityManager()->clear();

        try {
            $this->service(RollbackPageRevisionHandler::class)(new RollbackPageRevisionCommand($pageId, $revisionId));
            self::fail('Rollback of a broken snapshot must fail.');
        } catch (ValueError) {
        }

        $this->entityManager()->clear();
        $reloaded = $this->service(PageRepositoryInterface::class)->get($pageId);
        self::assertSame('Заборы', $reloaded->title());
        $names = array_map(static fn (PageBlock $block): string => $block->name(), $this->service(PageBlockRepositoryInterface::class)->findByPage($pageId));
        self::assertSame(['Первый', 'Второй'], $names);
        self::assertCount(1, $this->service(PageRevisionRepositoryInterface::class)->findByPage($pageId));
    }

    public function testCacheInvalidationRunsAfterCommitAndIsDroppedOnRollback(): void
    {
        $transactions = $this->service(TransactionRunnerInterface::class);
        $calls = new class () {
            /** @var list<string> */
            public array $items = [];
        };

        $transactions->run(function () use ($transactions, $calls): void {
            $transactions->run(function () use ($transactions, $calls): void {
                $transactions->afterCommit(static function () use ($calls): void {
                    $calls->items[] = 'nested';
                });
            });
            self::assertSame([], $calls->items, 'Вложенная транзакция не фиксирует внешнюю.');
        });
        self::assertSame(['nested'], $calls->items);

        try {
            $transactions->run(function () use ($transactions, $calls): void {
                $transactions->afterCommit(static function () use ($calls): void {
                    $calls->items[] = 'rolled back';
                });

                throw new RuntimeException('boom');
            });
        } catch (Throwable) {
        }
        self::assertSame(['nested'], $calls->items);
        self::assertTrue($this->entityManager()->isOpen(), 'Ошибка операции не закрывает EntityManager: пакетные операции продолжают работу.');

        $transactions->afterCommit(static function () use ($calls): void {
            $calls->items[] = 'immediate';
        });
        self::assertSame(['nested', 'immediate'], $calls->items);
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
