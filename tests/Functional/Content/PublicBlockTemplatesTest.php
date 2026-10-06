<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\Content\Application\Service\PageBlockView;
use App\Module\Content\UI\Web\TwigBlockRenderer;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Публичные шаблоны блоков: каждый тип должен отрисоваться своим шаблоном, а не молча упасть в default.html.twig
 * (рендерер перехватывает ошибки Twig, поэтому поломку шаблона иначе никто бы не заметил).
 */
final class PublicBlockTemplatesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        SchemaTestHelper::recreateSchema($entityManager);
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     */
    #[DataProvider('blocks')]
    public function testBlockUsesItsOwnTemplate(string $type, array $content, array $settings, string $expected): void
    {
        $html = $this->render($type, $content, $settings);

        self::assertStringContainsString($expected, $html);
        self::assertStringNotContainsString('<h2 class="zp-h2">Block</h2>', $html, \sprintf('Блок "%s" упал в default-шаблон.', $type));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, array<string, mixed>, string}>
     */
    public static function blocks(): iterable
    {
        yield 'hero.classic' => ['hero.classic', ['title' => 'Заголовок', 'subtitle' => 'Подзаголовок', 'text' => "Первое\nВторое", 'cta' => ['label' => 'Рассчитать', 'href' => '#lead']], [], 'href="#lead-form"'];
        yield 'hero (legacy)' => ['hero', ['title' => 'Старый hero', 'text' => 'Текст'], [], 'Старый hero'];
        yield 'rich-text' => ['rich-text', ['html' => '<p>Абзац</p>'], [], '<p>Абзац</p>'];
        yield 'text (legacy)' => ['text', ['title' => 'Заголовок', 'text' => '<p>Абзац</p>'], [], '<p>Абзац</p>'];
        yield 'text-with-image' => ['text-with-image', ['title' => 'Текст', 'text' => 'Строка'], [], 'data-block-type="text-with-image"'];
        yield 'features' => ['features', ['title' => 'Плюсы', 'items' => [['title' => 'Один', 'text' => 'Подробнее']]], [], 'data-block-type="features"'];
        yield 'pricing' => ['pricing', ['title' => 'Цены', 'items' => [['title' => 'Largo', 'price' => '3 400 ₽/м²', 'features' => ['Гладкий']]]], [], 'data-lead-plan="Largo"'];
        yield 'price-table' => ['price-table', ['columns' => ['Название', 'Цена'], 'rows' => [['Largo', '3 400']]], [], 'data-label="Цена"'];
        yield 'gallery' => ['gallery', ['items' => [['src' => '/uploads/a.jpg', 'alt' => 'Фото']]], [], 'data-lightbox'];
        yield 'portfolio' => ['portfolio', ['items' => [['title' => 'Жалюзи', 'image' => '/uploads/a.jpg', 'href' => '/zabor-jaluzi/']]], [], 'href="/zabor-jaluzi/"'];
        yield 'faq' => ['faq', ['items' => [['question' => 'Вопрос?', 'answer' => 'Ответ.']]], [], '"@type":"FAQPage"'];
        yield 'steps' => ['steps', ['title' => 'Этапы', 'items' => [['title' => 'Заявка', 'text' => 'Позвоните']]], [], 'data-block-type="steps"'];
        yield 'cta' => ['cta', ['title' => 'Позвоните', 'cta' => ['label' => 'Заказать', 'href' => '#lead']], [], 'data-lead-cta'];
        yield 'contact-form' => ['contact-form', ['title' => 'Своя форма'], [], 'id="lead-form"'];
        yield 'cta_form (legacy)' => ['cta_form', ['title' => 'Заявка'], [], 'id="lead-form"'];
        yield 'video (embed)' => ['video', ['url' => 'https://rutube.ru/play/embed/abc/', 'title' => 'Видео'], [], 'data-video-embed="https://rutube.ru/play/embed/abc/"'];
        yield 'video (file)' => ['video', ['url' => '/uploads/a.mp4'], [], 'preload="none"'];
        yield 'contacts-map' => ['contacts-map', ['title' => 'Контакты'], [], 'tel:+78452988808'];
        yield 'hero.minimal' => ['hero.minimal', ['title' => 'Контакты', 'subtitle' => 'Позвоните нам'], [], 'data-block-type="hero.minimal"'];
        yield 'image' => ['image', ['src' => '/uploads/a.jpg', 'alt' => 'Картинка'], [], 'alt="Картинка"'];
        yield 'reviews' => ['reviews', ['items' => [['author' => 'Андрей', 'text' => 'Поставили быстро.']]], [], 'data-block-type="reviews"'];
        yield 'fence-configurator' => ['fence-configurator', ['materials' => [['title' => 'Профнастил С8', 'pricePerMeter' => 1000]]], [], 'data-fence-configurator'];
    }

    public function testGalleryHidesExtraPhotosBehindButtonAndSupportsPortraitMode(): void
    {
        $items = [];
        for ($i = 1; $i <= 5; ++$i) {
            $items[] = ['src' => \sprintf('/uploads/%d.jpg', $i), 'alt' => \sprintf('Фото %d', $i)];
        }

        $html = $this->render('gallery', ['items' => $items], ['visible' => 3, 'aspect' => 'portrait', 'fit' => 'contain']);

        self::assertSame(2, substr_count($html, 'data-gallery-extra'));
        self::assertStringContainsString('Показать все фото (5)', $html);
        self::assertStringContainsString('aspect-3/4', $html);
        self::assertStringContainsString('object-contain', $html);
    }

    public function testHeroTakesPageHeadingOnlyWhenItIsFirstBlock(): void
    {
        $renderer = self::getContainer()->get(TwigBlockRenderer::class);
        self::assertInstanceOf(TwigBlockRenderer::class, $renderer);
        $block = new PageBlockView('b1', 'hero.classic', 'Первый экран', 0, ['title' => 'Над заголовком', 'subtitle' => 'Подзаголовок'], []);

        $withHeading = $renderer->render($block, true, 'Заборы в Саратове');
        $without = $renderer->render($block, false, null);

        self::assertStringContainsString('<h1', $withHeading);
        self::assertStringContainsString('Заборы в Саратове', $withHeading);
        self::assertStringContainsString('Над заголовком', $withHeading);
        self::assertStringNotContainsString('<h1', $without);
        self::assertStringContainsString('<h2', $without);
        self::assertTrue($renderer->consumesPageHeading('hero.classic'));
        self::assertFalse($renderer->consumesPageHeading('rich-text'));
    }

    public function testContactsBlockRendersOfficesHoursAndRequisitesWithCopyButtons(): void
    {
        $html = $this->render('contacts-map', [
            'offices' => [[
                'city' => 'Саратов',
                'role' => 'Головной офис',
                'address' => 'ул. Вешняя, 44/2',
                'phone' => '8 (8452) 98-88-08',
                'phoneHref' => 'tel:+78452988808',
                'email' => 'info@zaborprofil.ru',
                'timezone' => 'Europe/Saratov',
                'hours' => [
                    ['label' => 'Пн – Пт', 'days' => [1, 2, 3, 4, 5], 'open' => '09:00', 'close' => '18:00'],
                    ['label' => 'Вс', 'days' => [0], 'note' => 'выходной'],
                ],
            ]],
            'map' => ['url' => 'https://2gis.ru/saratov/firm/1', 'embedUrl' => 'https://widgets.2gis.com/widget?type=firmsonmap'],
            'requisites' => ['legalName' => 'ООО «Забор Профиль»', 'items' => [['label' => 'ИНН', 'value' => '6452128198'], ['label' => 'Пусто', 'value' => '']]],
        ]);

        self::assertStringContainsString('data-timezone="Europe/Saratov"', $html);
        self::assertStringContainsString('data-days="1,2,3,4,5" data-open="09:00" data-close="18:00"', $html);
        self::assertStringContainsString('выходной', $html);
        self::assertStringContainsString('data-copy="ул. Вешняя, 44/2"', $html);
        self::assertStringContainsString('href="tel:+78452988808"', $html);
        self::assertStringContainsString('data-map-embed="https://widgets.2gis.com/widget?type=firmsonmap"', $html);
        self::assertStringContainsString('data-copy="6452128198"', $html);
        self::assertSame(1, substr_count($html, 'aria-label="Скопировать: '), 'Реквизит без значения не выводится.');
    }

    public function testFenceConfiguratorCalculatesFirstPriceOnServer(): void
    {
        $html = $this->render('fence-configurator', [
            'title' => 'Соберите забор',
            'materials' => [
                ['title' => 'Профнастил С8', 'pricePerMeter' => 1290, 'pattern' => 'profnastil'],
                ['title' => 'Евроштакетник', 'pricePerMeter' => 2050, 'pattern' => 'shtaketnik'],
            ],
            'heights' => [['label' => '1,5 м', 'factor' => 0.88], ['label' => '1,8 м', 'factor' => 1]],
            'colors' => [
                ['ral' => '6005', 'name' => 'зелёный мох', 'hex' => '#0F4336'],
                ['ral' => '9999', 'name' => 'не цвет', 'hex' => 'red;background:url(x)'],
            ],
            'gate' => ['label' => 'Ворота и калитка', 'price' => 38000],
            'length' => ['min' => 10, 'max' => 200, 'default' => 40],
        ]);

        // 40 м × 1290 ₽ × коэффициент базовой высоты 1 = 51 600 ₽ (ворота по умолчанию не выбраны).
        self::assertStringContainsString("≈ 51\u{00A0}600\u{00A0}₽", $html);
        self::assertMatchesRegularExpression('/name="height" value="1" checked/u', $html, 'По умолчанию выбрана высота с коэффициентом 1.');
        self::assertStringContainsString('--ral: #0F4336', $html);
        self::assertStringNotContainsString('url(x)', $html, 'Цвет не из HEX в стиль не попадает.');
        self::assertStringContainsString('data-lead-plan="Конфигуратор: Профнастил С8, RAL 6005, высота 1,8 м, длина 40 м', $html);
        self::assertStringContainsString('Ворота и калитка', $html);
    }

    public function testFenceConfiguratorWithoutPriceAsksForRequest(): void
    {
        $html = $this->render('fence-configurator', ['materials' => [['title' => 'Жалюзи', 'pricePerMeter' => 0, 'pattern' => 'jaluzi']]]);

        self::assertStringContainsString('Цена по запросу', $html);
        self::assertStringContainsString('data-pattern="jaluzi"', $html);
        self::assertStringNotContainsString('name="gate"', $html, 'Без цены ворот пункт не показывается.');
    }

    public function testAudienceSettingWrapsBlockForTheSwitch(): void
    {
        $items = ['items' => [['title' => 'Заявка']]];

        self::assertStringStartsWith('<div data-audience="b2b">', $this->render('steps', $items, ['audience' => 'b2b']));
        self::assertStringStartsWith('<div data-audience="b2c">', $this->render('steps', $items, ['audience' => 'b2c']));
        self::assertStringNotContainsString('data-audience', $this->render('steps', $items, ['audience' => '']), 'Блок для всех не оборачивается.');
        self::assertStringNotContainsString('data-audience', $this->render('steps', $items, ['audience' => 'admins']), 'Неизвестное значение игнорируется.');
        self::assertSame('', trim($this->render('steps', ['items' => []], ['audience' => 'b2b'])), 'Пустой блок не превращается в пустую обёртку.');
    }

    public function testReviewsShowAuthorPlaceAndDetailsWithoutReviewMarkup(): void
    {
        $html = $this->render('reviews', ['title' => 'Отзывы', 'items' => [
            ['author' => 'Андрей', 'place' => 'Заводской район', 'details' => '28 м, Largo', 'text' => 'Поставили за два дня.', 'image' => '/uploads/a.jpg'],
            ['author' => 'Без текста', 'text' => ''],
        ]]);

        self::assertStringContainsString('Андрей, Заводской район', $html);
        self::assertStringContainsString('28 м, Largo', $html);
        self::assertStringContainsString('«Поставили за два дня.»', $html);
        self::assertStringNotContainsString('Без текста', $html, 'Отзыв без текста не выводится.');
        self::assertStringNotContainsString('"@type":"Review"', $html, 'Разметку Review для ручных отзывов не выводим.');
        self::assertSame('', trim($this->render('reviews', ['items' => []])), 'Пустой блок не выводится.');
    }

    public function testStepsShowBrandIconInsteadOfNumber(): void
    {
        $html = $this->render('steps', ['items' => [['title' => 'Замер', 'icon' => 'measure'], ['title' => 'Монтаж', 'icon' => 'unknown']]]);

        self::assertStringContainsString('href="#i-zp-measure"', $html);
        self::assertStringNotContainsString('#i-zp-unknown', $html, 'Неизвестная иконка не выводится, остаётся номер.');
    }

    public function testPriceMatrixRendersFirstProfilePricesAndFullTable(): void
    {
        $html = $this->render('price-matrix', [
            'title' => 'Прайс',
            'unit' => '₽/м²',
            'groups' => [
                [
                    'label' => 'С покрытием',
                    'rows' => ['С8', 'МП18'],
                    'filterLabel' => 'Класс',
                    'options' => [
                        ['title' => 'Полиэстер', 'group' => 'ECO', 'tone' => 'amber', 'specs' => [['label' => 'Гарантия', 'value' => '5 лет', 'chip' => 'гарантия 5 лет'], ['label' => 'Покрытие', 'value' => '—', 'chip' => '-']], 'prices' => ['С8' => 1455, 'МП18' => null]],
                        ['title' => 'VALORY', 'group' => 'ПРЕМЬЕР', 'tone' => 'orange', 'prices' => ['С8' => null, 'МП18' => 960]],
                    ],
                ],
                ['label' => 'Оцинкованный', 'compact' => true, 'rows' => ['С8'], 'options' => [['title' => '0.30 мм', 'prices' => ['С8' => 363]]]],
            ],
        ]);

        self::assertStringContainsString('role="tablist"', $html);
        self::assertStringContainsString("1\u{00A0}455", $html, 'Цена первого профиля показывается сразу, с неразрывным пробелом.');
        self::assertMatchesRegularExpression('/data-prices="[^"]*1455[^"]*null[^"]*"/u', $html, 'Цены всех профилей лежат в data-prices для JS.');
        self::assertStringContainsString('гарантия 5 лет', $html);
        self::assertStringNotContainsString('покрытие —', $html, 'Характеристика с chip="-" не выводится на карточке.');
        self::assertMatchesRegularExpression('/data-group="ПРЕМЬЕР"[^>]*hidden/u', $html, 'Вариант без цены для первого профиля скрыт.');
        self::assertStringContainsString('name="pm-b1-filter-0"', $html);
        self::assertStringContainsString('Показать полную таблицу цен', $html);
        self::assertStringContainsString('<th scope="col" class="text-right">МП18</th>', $html);
    }

    public function testFenceTypesSplitsBulletsAndSubtitle(): void
    {
        $html = $this->render('fence-types', ['items' => [['title' => 'VALORY', 'text' => "Дизайнерское решение\nОписание покрытия\n– толщина 30 мкм\n– отличная стойкость"]]]);

        self::assertStringContainsString('Дизайнерское решение', $html);
        self::assertStringContainsString('<span>толщина 30 мкм</span>', $html);
        self::assertSame(2, substr_count($html, '<li><svg'));
    }

    public function testInternalLinksRenderQuickNavigation(): void
    {
        $html = $this->render('internal-links', ['items' => [['title' => 'Прайс-лист', 'href' => '#prices'], ['title' => 'Пусто', 'href' => '']]]);

        self::assertStringContainsString('<a href="#prices">Прайс-лист</a>', $html);
        self::assertStringNotContainsString('Пусто', $html);
    }

    public function testLegacyContactsBlockStillShowsSiteContacts(): void
    {
        $html = $this->render('contacts', ['items' => [['label' => 'Склад', 'value' => 'Саратов']]]);

        self::assertStringContainsString('tel:+78452988808', $html);
        self::assertStringContainsString('Склад', $html);
    }

    public function testMinimalHeroTakesPageHeading(): void
    {
        $renderer = self::getContainer()->get(TwigBlockRenderer::class);
        self::assertInstanceOf(TwigBlockRenderer::class, $renderer);
        $html = $renderer->render(new PageBlockView('b1', 'hero.minimal', 'Шапка', 0, ['title' => 'Контакты'], []), true, 'Контакты — Забор Профиль');

        self::assertStringContainsString('<h1', $html);
        self::assertStringContainsString('Контакты — Забор Профиль', $html);
        self::assertTrue($renderer->consumesPageHeading('hero.minimal'));
    }

    public function testSiteContactsAreAvailableToEveryTemplate(): void
    {
        $html = $this->render('cta', ['title' => 'Звонок', 'cta' => ['label' => 'Заказать', 'href' => '#lead']], []);

        self::assertStringContainsString('href="tel:+78452988808"', $html);
        self::assertStringContainsString('8 (8452) 98-88-08', $html);
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     */
    private function render(string $type, array $content, array $settings = []): string
    {
        $renderer = self::getContainer()->get(TwigBlockRenderer::class);
        self::assertInstanceOf(TwigBlockRenderer::class, $renderer);

        return $renderer->render(new PageBlockView('b1', $type, 'Block', 0, $content, $settings));
    }
}
