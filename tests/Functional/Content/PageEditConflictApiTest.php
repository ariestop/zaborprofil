<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Tests\Support\Admin\AdminApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class PageEditConflictApiTest extends AdminApiTestCase
{
    private const string BLOCK_ID = '01JQY3Y5SFSVCE00H9GQWQY56V';

    public function testBuilderDocumentExposesStableVersionAcrossReads(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'edit-version');

        $this->api($client, 'GET', $this->url($pageId, 'builder'));
        self::assertResponseIsSuccessful();
        $empty = $this->json($client);
        self::assertIsString($empty['version']);

        $this->api($client, 'PUT', $this->url($pageId, 'builder'), ['baseVersion' => $empty['version'], 'blocks' => [$this->heroBlock('Заголовок')]]);
        self::assertResponseIsSuccessful();
        $saved = $this->json($client);
        self::assertNotSame($empty['version'], $saved['version']);

        $this->api($client, 'GET', $this->url($pageId, 'builder'));
        self::assertSame($saved['version'], $this->json($client)['version']);
    }

    public function testSaveWithStaleBaseVersionReturnsConflictAndKeepsServerBlocks(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'edit-conflict');

        $this->api($client, 'GET', $this->url($pageId, 'builder'));
        $baseVersion = $this->text($this->json($client)['version']);

        $this->api($client, 'PUT', $this->url($pageId, 'builder'), ['baseVersion' => $baseVersion, 'blocks' => [$this->heroBlock('Правка первого редактора')]]);
        self::assertResponseIsSuccessful();
        $currentVersion = $this->text($this->json($client)['version']);

        $this->api($client, 'PUT', $this->url($pageId, 'builder'), ['baseVersion' => $baseVersion, 'blocks' => [$this->heroBlock('Правка второго редактора')]]);
        self::assertResponseStatusCodeSame(409);
        $conflict = $this->json($client);
        self::assertSame('EDIT_CONFLICT', $conflict['code']);
        self::assertSame($currentVersion, $conflict['version']);
        self::assertIsString($conflict['updatedAt']);

        $this->api($client, 'GET', $this->url($pageId, 'builder'));
        $document = $this->json($client);
        self::assertSame($currentVersion, $document['version']);
        $blocks = $this->rows($document['blocks']);
        self::assertSame('Правка первого редактора', $this->rows([$blocks[0]['content']])[0]['title']);

        $this->api($client, 'PUT', $this->url($pageId, 'builder'), ['baseVersion' => $currentVersion, 'blocks' => [$this->heroBlock('Правка второго редактора')]]);
        self::assertResponseIsSuccessful();
    }

    public function testSaveWithoutBaseVersionStaysBackwardCompatibleAndRejectsInvalidType(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'edit-legacy');

        $this->api($client, 'PUT', $this->url($pageId, 'builder'), ['blocks' => [$this->heroBlock('Без версии')]]);
        self::assertResponseIsSuccessful();

        $this->api($client, 'PUT', $this->url($pageId, 'builder'), ['baseVersion' => 123, 'blocks' => []]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testEditLockWarnsSecondSessionAndAllowsTakeOver(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'edit-lock');
        $first = 'tab-first-0001';
        $second = 'tab-second-0002';

        $this->api($client, 'POST', $this->url($pageId, 'edit-lock'), ['sessionId' => $first]);
        self::assertResponseIsSuccessful();
        $status = $this->json($client);
        self::assertTrue($status['ownedByMe']);
        self::assertFalse($status['locked']);

        $this->api($client, 'POST', $this->url($pageId, 'edit-lock'), ['sessionId' => $second]);
        self::assertResponseIsSuccessful();
        $status = $this->json($client);
        self::assertTrue($status['locked']);
        self::assertFalse($status['ownedByMe']);
        $holder = $status['holder'];
        self::assertIsArray($holder);
        self::assertSame('admin-api@example.test', $holder['label']);
        self::assertTrue($holder['isSelf']);

        $this->api($client, 'POST', $this->url($pageId, 'edit-lock'), ['sessionId' => $first]);
        self::assertTrue($this->json($client)['ownedByMe']);

        $this->api($client, 'POST', $this->url($pageId, 'edit-lock'), ['sessionId' => $second, 'takeOver' => true]);
        self::assertTrue($this->json($client)['ownedByMe']);

        $this->api($client, 'POST', $this->url($pageId, 'edit-lock'), ['sessionId' => $first]);
        self::assertTrue($this->json($client)['locked']);

        $this->api($client, 'DELETE', $this->url($pageId, 'edit-lock'), ['sessionId' => $second]);
        self::assertResponseStatusCodeSame(204);

        $this->api($client, 'POST', $this->url($pageId, 'edit-lock'), ['sessionId' => $first]);
        self::assertTrue($this->json($client)['ownedByMe']);
    }

    public function testEditLockValidatesInputAndRequiresExistingPage(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'edit-lock-input');

        $this->api($client, 'POST', $this->url($pageId, 'edit-lock'), ['sessionId' => 'x']);
        self::assertResponseStatusCodeSame(422);

        $this->api($client, 'POST', '/admin/api/content/pages/01JQY3Y5SFSVCE00H9GQWQY56V/edit-lock', ['sessionId' => 'tab-first-0001']);
        self::assertResponseStatusCodeSame(404);
    }

    private function createPage(KernelBrowser $client, string $slug): string
    {
        $this->api($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Страница '.$slug,
            'slug' => $slug,
            'path' => '/'.$slug.'/',
            'h1' => 'Страница '.$slug,
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->text($this->json($client)['id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function heroBlock(string $title): array
    {
        return [
            'id' => self::BLOCK_ID,
            'type' => 'hero.classic',
            'enabled' => true,
            'position' => 0,
            'content' => ['title' => $title, 'subtitle' => 'Подзаголовок', 'text' => 'Текст'],
            'settings' => [],
        ];
    }

    private function url(string $pageId, string $suffix): string
    {
        return '/admin/api/content/pages/'.$pageId.'/'.$suffix;
    }
}
