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
        yield 'image' => ['image', ['src' => '/uploads/a.jpg', 'alt' => 'Картинка'], [], 'alt="Картинка"'];
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
