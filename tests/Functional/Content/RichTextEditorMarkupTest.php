<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Tests\Support\Admin\AdminApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Разметка, которую выдаёт редактор rich-text (TipTap), должна переживать сохранение и вывод на сайте.
 *
 * Верхние и нижние индексы (м², м³, H₂O) разрешены. Чего нет в белом списке санитайзера, редактор не предлагает:
 * иначе правка выглядела бы применённой, а после сохранения молча пропадала.
 */
final class RichTextEditorMarkupTest extends AdminApiTestCase
{
    public function testSuperscriptAndSubscriptSurviveSavingAndPublicRendering(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'indeksy');

        $this->api($client, 'PUT', \sprintf('/admin/api/content/pages/%s/builder', $pageId), [
            'blocks' => [[
                'type' => 'rich-text',
                'enabled' => true,
                'content' => ['html' => '<p>Цена за 1 м<sup>2</sup>, формула H<sub>2</sub>O</p>'],
                'settings' => [],
            ]],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('<p>Цена за 1 м<sup>2</sup>, формула H<sub>2</sub>O</p>', $this->savedHtml($client));

        $this->api($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();
        $client->request('GET', '/indeksy/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('м<sup>2</sup>, формула H<sub>2</sub>O', (string) $client->getResponse()->getContent());
    }

    public function testMarkupOutsideTheWhitelistIsStillRemoved(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'razmetka');

        $this->api($client, 'PUT', \sprintf('/admin/api/content/pages/%s/builder', $pageId), [
            'blocks' => [[
                'type' => 'rich-text',
                'enabled' => true,
                'content' => ['html' => '<h1>Заголовок</h1><p><mark>выделено</mark> <s>зачёркнуто</s> <code>код</code></p><hr><p><img src="/uploads/media/a.png" alt="a"></p><sup onclick="alert(1)">1</sup>'],
                'settings' => [],
            ]],
        ]);
        self::assertResponseIsSuccessful();
        $html = $this->savedHtml($client);

        self::assertStringNotContainsString('<h1', $html);
        self::assertStringNotContainsString('<mark', $html);
        self::assertStringNotContainsString('<s>', $html);
        self::assertStringNotContainsString('<code', $html);
        self::assertStringNotContainsString('<hr', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringContainsString('выделено', $html);
        self::assertStringContainsString('<sup>1</sup>', $html);
    }

    private function createPage(KernelBrowser $client, string $slug): string
    {
        $this->api($client, 'POST', '/admin/api/content/pages', [
            'type' => 'text_page',
            'title' => $slug,
            'slug' => $slug,
            'path' => \sprintf('/%s/', $slug),
            'h1' => $slug,
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->json($client)['id'] ?? null;
        self::assertIsString($pageId);

        return $pageId;
    }

    private function savedHtml(KernelBrowser $client): string
    {
        $blocks = $this->json($client)['blocks'] ?? null;
        self::assertIsArray($blocks);
        $block = $blocks[0] ?? null;
        self::assertIsArray($block);
        $content = $block['content'] ?? null;
        self::assertIsArray($content);
        $html = $content['html'] ?? null;
        self::assertIsString($html);

        return $html;
    }
}
