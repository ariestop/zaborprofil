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
use App\Module\Menu\Application\Command\MenuItemInput;
use App\Module\Menu\Application\Service\MenuTreeService;
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
        // Боковое меню телефона вне <header>: у шапки backdrop-filter, и fixed-панель считалась бы от шапки, а не от экрана.
        self::assertSame('dialog', $crawler->filter('#mobile-nav')->attr('role'));
        self::assertSame(0, $crawler->filter('header #mobile-nav')->count());
        self::assertGreaterThan(0, $crawler->filter('#mobile-nav button[data-nav-close]')->count(), 'У меню есть крестик.');
        self::assertGreaterThan(0, $crawler->filter('#mobile-nav nav a')->count());
        self::assertGreaterThan(0, $crawler->filter('#mobile-nav nav a use[href="#i-zp-fence"]')->count(), 'У пунктов бокового меню фирменные иконки: «Забор Жалюзи» — забор.');
        self::assertGreaterThan(0, $crawler->filter('[data-callbar] a[href="tel:+78452988808"]')->count());
        self::assertGreaterThan(0, $crawler->filter('footer#contacts a[href="mailto:info@zaborprofil.ru"]')->count());

        // Контакты в разметке Organization берутся из того же глобала site, что и шапка.
        $organization = json_decode($crawler->filter('script[type="application/ld+json"]')->first()->text(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($organization);
        self::assertSame('Organization', $organization['@type'] ?? null);
        self::assertSame('+78452988808', $organization['telephone'] ?? null);
        self::assertSame('info@zaborprofil.ru', $organization['email'] ?? null);
    }

    public function testHeaderMenuFallsBackToMainSectionsUntilMenuIsFilled(): void
    {
        $client = self::createClient();
        $this->createPage('/zabory/', 'Заборы', [[BlockType::RichText, ['html' => '<p>Текст</p>']]]);

        $crawler = $client->request('GET', '/zabory/');

        self::assertGreaterThan(0, $crawler->filter('nav[aria-label="Основная навигация"] a[href="/zabor-jaluzi/"]')->count());
    }

    public function testHeaderSectionsOpenCompactListsWithPricesAndDrawerAccordion(): void
    {
        $client = self::createClient();
        $this->createPage('/zabory/', 'Заборы', [[BlockType::RichText, ['html' => '<p>Текст</p>']]]);
        $menu = $this->menuTree();
        $fences = $menu->create(new MenuItemInput('header', 'Заборы', '/zabory/'));
        $menu->create(new MenuItemInput('header', 'Забор Жалюзи', '/zabor-jaluzi/', 'от 3 400 ₽/м²', parentId: $fences->id()->toBase32()));
        $menu->create(new MenuItemInput('header', 'Забор Ранчо', '/gorizont/', 'Горизонтальные панели', parentId: $fences->id()->toBase32()));
        $menu->create(new MenuItemInput('header', 'Скрытый', '/hidden/', active: false, parentId: $fences->id()->toBase32()));
        $materials = $menu->create(new MenuItemInput('header', 'Материалы', '/fasad/profnastil/'));
        $menu->create(new MenuItemInput('header', 'Профнастил', '/fasad/profnastil/', parentId: $materials->id()->toBase32()));
        $menu->create(new MenuItemInput('header', 'Контакты', '/contacts/'));

        $crawler = $client->request('GET', '/zabory/');

        self::assertResponseIsSuccessful();
        $nav = $crawler->filter('nav[aria-label="Основная навигация"]');
        $triggers = $nav->filter('button[data-nav-trigger]');
        self::assertSame(['Заборы', 'Материалы'], $triggers->each(static fn ($node): string => trim($node->text())));
        self::assertSame('false', $triggers->first()->attr('aria-expanded'));

        $list = $crawler->filter('#'.$triggers->first()->attr('aria-controls'));
        self::assertNotNull($list->attr('hidden'), 'Список закрыт, пока его не раскрыли.');
        self::assertSame(['/zabor-jaluzi/', '/gorizont/'], $list->filter('.zp-nav__list a')->each(static fn ($node): ?string => $node->attr('href')), 'Скрытый подпункт на сайт не попадает.');
        self::assertSame('от 3 400 ₽/м²', $list->filter('a[href="/zabor-jaluzi/"] .zp-nav__price')->text(), 'Подпись с цифрами — цена справа.');
        self::assertSame('Горизонтальные панели', $list->filter('a[href="/gorizont/"] .zp-nav__hint')->text(), 'Текстовая подпись — под названием.');
        self::assertSame('/zabory/', $list->filter('a.zp-nav__all')->attr('href'));
        self::assertStringContainsString('Все заборы', $list->filter('a.zp-nav__all')->text());
        self::assertSame(0, $crawler->filter('#'.$triggers->eq(1)->attr('aria-controls').' a.zp-nav__all')->count(), 'Ссылка «Все …» не дублирует подпункт с тем же адресом.');
        self::assertSame(1, $nav->filter('a.zp-nav__item[href="/contacts/"]')->count());

        $drawer = $crawler->filter('#mobile-nav nav');
        self::assertSame(2, $drawer->filter('details.zp-drawer__group')->count());
        self::assertNotNull($drawer->filter('details.zp-drawer__group')->first()->attr('open'), 'Первый раздел в боковом меню раскрыт.');
        self::assertSame(1, $drawer->filter('details a[href="/gorizont/"]')->count());
        self::assertSame(1, $drawer->filter(':scope > a[href="/contacts/"]')->count());
    }

    public function testReferenceHomeOutputsOneHeadingAndOneLeadForm(): void
    {
        $client = self::createClient();
        $this->createPage('/', 'Заборы в Саратове под ключ', [
            [BlockType::HeroClassic, ['title' => 'Над заголовком', 'subtitle' => 'Подзаголовок', 'cta' => ['label' => 'Рассчитать', 'href' => '#lead-form']]],
            [BlockType::ContactForm, ['title' => 'Рассчитаем стоимость']],
        ], 'home');

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
        self::assertStringContainsString('Хороший забор', $crawler->filter('h1')->text());
        self::assertGreaterThan(0, $crawler->filter('.ref-hero h1')->count());
        self::assertCount(1, $crawler->filter('#lead-form'), 'Форма заявки не должна дублироваться внизу страницы.');
    }

    public function testReferenceHomeHasWorkingTargetsAndExistingLeadFormContract(): void
    {
        $client = self::createClient();
        $this->createPage('/', 'Заборы в Саратове под ключ', [
            [BlockType::HeroClassic, ['title' => 'Производство', 'subtitle' => 'Описание из CMS']],
            [BlockType::Portfolio, ['title' => 'Каталог из CMS', 'items' => [
                ['title' => 'Жалюзи', 'href' => '/zabor-jaluzi/', 'price' => 'от 3 400 ₽/м²'],
                ['title' => 'Заглушки', 'href' => '/zaglushki-plastik/', 'price' => 'от 5 ₽/шт.'],
            ]]],
            [BlockType::Features, ['title' => 'Преимущества из CMS', 'items' => [
                ['title' => 'Монтаж', 'text' => 'Уникальное описание преимущества'],
            ]]],
            [BlockType::Steps, ['title' => 'Этапы из CMS', 'items' => [
                ['title' => 'Расчёт', 'text' => 'Уточнение комплектации'],
            ]]],
            [BlockType::Gallery, ['title' => 'Объекты из CMS', 'items' => [
                ['src' => '/img/logo.svg', 'alt' => 'Тестовый объект'],
            ]]],
            [BlockType::RichText, ['html' => '<p>Сохранённый текст о компании</p>']],
            [BlockType::ContactForm, ['title' => 'Получить расчёт']],
        ], 'home');

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('body.ref-page'));
        self::assertCount(1, $crawler->filter('h1'));
        self::assertCount(4, $crawler->filter('.ref-product'));
        self::assertCount(3, $crawler->filter('.ref-project'));
        foreach ($crawler->filter('a[data-ref-open]') as $link) {
            self::assertCount(1, $crawler->filter('dialog#'.$link->getAttribute('data-ref-open')));
        }
        self::assertCount(1, $crawler->filter('#ref-enquiry #lead-form'));
        self::assertStringContainsString('визуализации', $crawler->filter('.ref-projects__aside')->text());
        self::assertCount(1, $crawler->filter('#lead-form'));
        self::assertSame('/api/leads', $crawler->filter('#lead-form form')->attr('action'));
        self::assertCount(1, $crawler->filter('#lead-form input[name="consent"]'));
        self::assertCount(1, $crawler->filter('#lead-form input[name="website"]'));
    }

    public function testPageWithoutFirstScreenGetsStandaloneHeadingAndBottomForm(): void
    {
        $client = self::createClient();
        $this->createPage('/o-kompanii/', 'О компании', [[BlockType::RichText, ['html' => '<p>Текст</p>']]]);

        $crawler = $client->request('GET', '/o-kompanii/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
        self::assertCount(0, $crawler->filter('.zp-hero'));
        self::assertCount(0, $crawler->filter('.zp-home'), 'Внутренние страницы сохраняют прежний шаблон.');
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

    private function menuTree(): MenuTreeService
    {
        $service = self::getContainer()->get(MenuTreeService::class);

        return $service instanceof MenuTreeService ? $service : throw new LogicException('MenuTreeService service is not available.');
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
