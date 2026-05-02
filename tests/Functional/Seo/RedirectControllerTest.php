<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RedirectControllerTest extends WebTestCase
{
    public function testActiveRedirectReturnsConfiguredResponse(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $this->redirectRepository()->save(new Redirect('/old-page/', '/new-page/', 301));

        $client->request('GET', '/old-page/');

        self::assertResponseRedirects('/new-page/', 301);
    }

    public function testPagePathChangeCreatesRedirect(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $pageRepository = $this->pageRepository();
        $page = new Page(PageType::Landing, 'Заборы', 'fences', '/old-fences/', 'Заборы');
        $pageRepository->save($page);

        $page->update(PageType::Landing, 'Заборы', 'fences', '/new-fences/', 'Заборы', 'default', 0, true);
        $pageRepository->save($page);

        $client->request('GET', '/old-fences/');

        self::assertResponseRedirects('/new-fences/', 301);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }

    private function redirectRepository(): RedirectRepositoryInterface
    {
        $repository = self::getContainer()->get(RedirectRepositoryInterface::class);

        if (!$repository instanceof RedirectRepositoryInterface) {
            throw new LogicException('Redirect repository service is not available.');
        }

        return $repository;
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
