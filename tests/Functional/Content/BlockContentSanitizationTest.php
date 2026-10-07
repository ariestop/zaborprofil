<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\Content\Application\Service\StructuredBlockDocumentService;
use App\Module\Content\Domain\Enum\BlockType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Очистка содержимого блоков перед сохранением: HTML блоков rich-text проходит через HtmlSanitizer,
 * текстовые поля, которые шаблоны экранируют, не меняются (иначе `&` и кавычки показались бы как сущности).
 */
final class BlockContentSanitizationTest extends KernelTestCase
{
    public function testRichTextHtmlLosesScriptsHandlersAndJavascriptLinks(): void
    {
        $content = $this->service()->sanitizeContent(BlockType::RichText, [
            'html' => '<h2 style="text-align: right">Заголовок</h2><p>Текст <strong>жирный</strong> <a href="https://zaborprofil.ru/" target="_blank" rel="noopener">сайт</a></p>'
                .'<p onclick=\'alert(1)\'>клик</p><a href="#" onmouseover = "alert(2)">наведи</a><a href=\'javascript:alert(3)\'>js</a>'
                .'<script>alert(4)</script><ul><li>пункт</li></ul><div>обёртка</div>',
        ]);

        $html = $content['html'];
        self::assertIsString($html);
        self::assertStringNotContainsString('alert', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<div', $html);
        self::assertStringContainsString('<h2 style="text-align: right">Заголовок</h2>', $html);
        self::assertStringContainsString('<strong>жирный</strong>', $html);
        self::assertStringContainsString('<a href="https://zaborprofil.ru/" target="_blank" rel="noopener">сайт</a>', $html);
        self::assertStringContainsString('<ul><li>пункт</li></ul>', $html);
        self::assertStringContainsString('обёртка', $html);
    }

    public function testLegacyTextBlockHtmlIsSanitizedToo(): void
    {
        $content = $this->service()->sanitizeContent(BlockType::Text, ['text' => '<p onclick=\'alert(1)\'>Абзац</p>']);

        self::assertSame('<p>Абзац</p>', $content['text']);
    }

    public function testEscapedTextFieldsKeepAmpersandsAndQuotes(): void
    {
        $content = $this->service()->sanitizeContent(BlockType::Hero, ['title' => 'Забор', 'text' => 'ООО "Ромашка" & Ко']);

        self::assertSame('ООО "Ромашка" & Ко', $content['text']);
    }

    public function testLongRichTextIsNotTruncated(): void
    {
        $paragraph = '<p>'.str_repeat('Длинный текст статьи о заборах. ', 20).'</p>';
        $html = str_repeat($paragraph, 60);
        self::assertGreaterThan(20_000, \strlen($html));

        $content = $this->service()->sanitizeContent(BlockType::RichText, ['html' => $html]);

        self::assertSame($html, $content['html']);
    }

    private function service(): StructuredBlockDocumentService
    {
        self::bootKernel();
        $service = self::getContainer()->get(StructuredBlockDocumentService::class);
        self::assertInstanceOf(StructuredBlockDocumentService::class, $service);

        return $service;
    }
}
