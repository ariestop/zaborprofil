<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Module\Content\Application\Service\PagePublisher;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageRevision;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Номер версии считается как MAX(version) + 1: два одновременных сохранения получили бы один номер.
 * Уникальный индекс `(page_id, version)` не даёт записать вторую ревизию с тем же номером.
 */
final class PageRevisionVersionUniquenessTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        SchemaTestHelper::recreateSchema($this->entityManager());
    }

    public function testSecondRevisionWithSameVersionIsRejected(): void
    {
        $page = new Page(PageType::Landing, 'Заборы', 'fences', '/fences/', 'Заборы');
        $this->service(PageRepositoryInterface::class)->save($page);
        $first = $this->service(PagePublisher::class)->snapshot($page, null, null, 'manual');
        self::assertSame(1, $first->version());

        // Второй запрос прочитал MAX(version) до того, как первый записал свою ревизию.
        $concurrent = new PageRevision($page, 1, 'Заборы', 'Заборы', 'fences', '/fences/', 'landing', 'default', 'draft', [], [], []);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->service(PageRevisionRepositoryInterface::class)->save($concurrent);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service(EntityManagerInterface::class);
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
}
