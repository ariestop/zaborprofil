<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Module\Content\Application\Command\SavePageBuilderDocumentCommand;
use App\Module\Content\Application\Handler\SavePageBuilderDocumentHandler;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Сохранение конструктора блокирует строку страницы до конца транзакции. Без этого два почти одновременных
 * сохранения с одной baseVersion оба проходили проверку версии, и второе молча затирало первое.
 */
final class BuilderSaveRowLockTest extends KernelTestCase
{
    public function testSaveWaitsForPageRowLockedByAnotherTransaction(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        SchemaTestHelper::recreateSchema($entityManager);
        $page = new Page(PageType::Landing, 'Заборы', 'fences', '/fences/', 'Заборы');
        $pages = self::getContainer()->get(PageRepositoryInterface::class);
        self::assertInstanceOf(PageRepositoryInterface::class, $pages);
        $pages->save($page);
        $handler = self::getContainer()->get(SavePageBuilderDocumentHandler::class);
        self::assertInstanceOf(SavePageBuilderDocumentHandler::class, $handler);

        // Другой запрос уже сохраняет эту страницу: его транзакция держит строку.
        $other = DriverManager::getConnection($entityManager->getConnection()->getParams());
        $other->beginTransaction();
        $other->executeQuery('SELECT id FROM content_pages WHERE id = ? FOR UPDATE', [$page->id()->toBinary()]);
        $entityManager->getConnection()->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $handler(new SavePageBuilderDocumentCommand((string) $page->id(), [], null));
            self::fail('Saving must wait for the page row locked by another transaction.');
        } catch (LockWaitTimeoutException) {
            $this->addToAssertionCount(1);
        } finally {
            $other->rollBack();
            $other->close();
        }
    }
}
