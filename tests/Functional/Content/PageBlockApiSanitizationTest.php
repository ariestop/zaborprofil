<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Tests\Support\Admin\AdminApiTestCase;

/**
 * API отдельных блоков (`/admin/api/content/pages/{id}/blocks`, `/admin/api/content/blocks/{id}`) раньше сохранял
 * HTML rich-text без очистки — в обход санитайзера конструктора.
 */
final class PageBlockApiSanitizationTest extends AdminApiTestCase
{
    private const string PAYLOAD = '<p onclick=\'alert(1)\'>Текст</p><script>alert(2)</script><a href=\'javascript:alert(3)\'>ссылка</a>';

    public function testCreateAndUpdateSanitizeRichTextHtml(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Страница',
            'slug' => 'stranica',
            'path' => '/stranica/',
            'h1' => 'Страница',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->json($client)['id'] ?? null;
        self::assertIsString($pageId);

        $this->api($client, 'POST', \sprintf('/admin/api/content/pages/%s/blocks', $pageId), [
            'type' => 'rich-text',
            'name' => 'Текст',
            'position' => 0,
            'content' => ['html' => self::PAYLOAD],
            'settings' => [],
            'isEnabled' => true,
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->json($client);
        $this->assertSafe($created['content'] ?? null);

        $blockId = $created['id'] ?? null;
        self::assertIsString($blockId);
        $this->api($client, 'PUT', \sprintf('/admin/api/content/blocks/%s', $blockId), [
            'type' => 'rich-text',
            'name' => 'Текст',
            'content' => ['html' => self::PAYLOAD],
            'settings' => [],
            'isEnabled' => true,
        ]);
        self::assertResponseIsSuccessful();
        $this->assertSafe($this->json($client)['content'] ?? null);
    }

    private function assertSafe(mixed $content): void
    {
        self::assertIsArray($content);
        $html = $content['html'] ?? null;
        self::assertIsString($html);
        self::assertStringNotContainsString('alert', $html);
        self::assertStringContainsString('<p>Текст</p>', $html);
    }
}
