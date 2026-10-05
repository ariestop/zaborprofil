<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\ValueObject\PageVisibility;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Общий каркас публичного сайта: шапка с телефоном, мобильное меню, панель связи, подвал и вывод H1.
 */
final class PublicSiteLayoutTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testLayoutHasPhoneMobileMenuCallbarAndFooterContacts(): void
    {
        $client = self::createClient();
        $this->createPage('/zabor-jaluzi/', 'Забор Жалюзи', [
            [BlockType::RichText, ['html' => '<p>Текст</p>']],
        ]);

        $crawler = $client->request('GET', '/zabor-jaluzi/');

        self::assertResponseIsSuccessful();
        self::assertSame('width=device-width, initial-scale=1, viewport-fit=cover', $crawler->filter('meta[name="viewport"]')->attr('content'));
        self::assertGreaterThan(0, $crawler->filter('a.zp-skip[href="#content"]')->count());
        self::assertSame('tel:+78452988808', $crawler->filter('header a.zp-header__phone')->attr('href'));
        self::assertSame('mobile-nav', $crawler->filter('button[data-nav-toggle]')->attr('aria-controls'));
        self::assertNotNull($crawler->filter('#mobile-nav')->attr('hidden'));
        self::assertGreaterThan(0, $crawler->filter('[data-callbar] a[href="tel:+78452988808"]')->count());
        self::assertGreaterThan(0, $crawler->filter('footer#contacts a[href="mailto:info@zaborprofil.ru"]')->count());
    }

    public function testHeaderMenuFallsBackToMainSectionsUntilMenuIsFilled(): void
    {
        $client = self::createClient();
        $this->createPage('/zabory/', 'Заборы', [[BlockType::RichText, ['html' => '<p>Текст</p>']]]);

        $crawler = $client->request('GET', '/zabory/');

        self::assertGreaterThan(0, $crawler->filter('nav[aria-label="Основная навигация"] a[href="/zabor-jaluzi/"]')->count());
    }

    public function testFirstScreenBlockOutputsPageHeadingExactlyOnce(): void
    {
        $client = self::createClient();
        $this->createPage('/', 'Заборы в Саратове под ключ', [
            [BlockType::HeroClassic, ['title' => 'Над заголовком', 'subtitle' => 'Подзаголовок', 'cta' => ['label' => 'Рассчитать', 'href' => '#lead-form']]],
            [BlockType::ContactForm, ['title' => 'Рассчитаем стоимость']],
        ], 'home');

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
        self::assertSame('Заборы в Саратове под ключ', trim($crawler->filter('h1')->text()));
        self::assertGreaterThan(0, $crawler->filter('.zp-hero h1')->count());
        self::assertCount(1, $crawler->filter('#lead-form'), 'Форма заявки не должна дублироваться внизу страницы.');
    }

    public function testPageWithoutFirstScreenGetsStandaloneHeadingAndBottomForm(): void
    {
        $client = self::createClient();
        $this->createPage('/o-kompanii/', 'О компании', [[BlockType::RichText, ['html' => '<p>Текст</p>']]]);

        $crawler = $client->request('GET', '/o-kompanii/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
        self::assertCount(0, $crawler->filter('.zp-hero'));
        self::assertCount(1, $crawler->filter('#lead-form'));
    }

    public function testLeadFormHasMobileFriendlyFields(): void
    {
        $client = self::createClient();
        $this->createPage('/o-kompanii/', 'О компании', [[BlockType::RichText, ['html' => '<p>Текст</p>']]]);

        $crawler = $client->request('GET', '/o-kompanii/');

        $phone = $crawler->filter('#lead-form input[name="phone"]');
        self::assertSame('tel', $phone->attr('type'));
        self::assertSame('tel', $phone->attr('inputmode'));
        self::assertSame('tel', $phone->attr('autocomplete'));
        self::assertSame('name', $crawler->filter('#lead-form input[name="name"]')->attr('autocomplete'));
        self::assertCount(1, $crawler->filter('#lead-form input[name="website"]'), 'Honeypot должен остаться.');
    }

    /**
     * @param list<array{BlockType, array<string, mixed>}> $blocks
     */
    private function createPage(string $path, string $title, array $blocks, string $type = 'landing'): void
    {
        SchemaTestHelper::recreateSchema($this->entityManager());

        $page = new Page(PageType::from($type), $title, trim($path, '/') === '' ? 'home' : trim($path, '/'), $path, $title);
        $page->publish();
        $this->pageRepository()->save($page);

        $entities = [];
        foreach ($blocks as $position => [$blockType, $content]) {
            $entities[] = new PageBlock($page, $blockType, $blockType->value, $position, $content, [], true, PageVisibility::Public);
        }
        $this->blockRepository()->saveAll($entities);
    }

    private function pageRepository(): PageRepositoryInterface
    {
        $repository = self::getContainer()->get(PageRepositoryInterface::class);

        return $repository instanceof PageRepositoryInterface ? $repository : throw new LogicException('PageRepositoryInterface service is not available.');
    }

    private function blockRepository(): PageBlockRepositoryInterface
    {
        $repository = self::getContainer()->get(PageBlockRepositoryInterface::class);

        return $repository instanceof PageBlockRepositoryInterface ? $repository : throw new LogicException('PageBlockRepositoryInterface service is not available.');
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager instanceof EntityManagerInterface ? $entityManager : throw new LogicException('EntityManagerInterface service is not available.');
    }
}
