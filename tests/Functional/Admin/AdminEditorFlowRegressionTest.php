<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminEditorFlowRegressionTest extends WebTestCase
{
    public function testAdminEditorFlowFromPagesToBuilderAndPreview(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('editor-flow@example.test', ['ROLE_ADMIN']));

        $client->request('GET', '/admin/pages');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app');

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Flow Page',
            'slug' => 'flow-page',
            'path' => '/flow-page/',
            'h1' => 'Flow Page',
            'template' => 'default',
            'sortOrder' => 0,
            'isIndexable' => true,
            'visibility' => 'public',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/'.$pageId.'/blocks', [
            'type' => 'hero',
            'name' => 'Hero block',
            'position' => 0,
            'content' => ['title' => 'Hero title'],
            'settings' => [],
            'isEnabled' => true,
            'visibility' => 'public',
        ]);
        self::assertResponseStatusCodeSame(201);
        $firstBlockId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/'.$pageId.'/blocks', [
            'type' => 'text',
            'name' => 'Text block',
            'position' => 1,
            'content' => ['richText' => '<p>Before update</p>'],
            'settings' => [],
            'isEnabled' => true,
            'visibility' => 'public',
        ]);
        self::assertResponseStatusCodeSame(201);
        $secondBlockId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $client->request('GET', '/admin/pages/'.$pageId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app');

        $client->request('GET', '/admin/pages/'.$pageId.'/builder');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app');

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/'.$pageId.'/blocks/reorder', [
            'blockIds' => [$secondBlockId, $firstBlockId],
        ]);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame($secondBlockId, $payload['blocks'][0]['id'] ?? null);

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/content/blocks/'.$secondBlockId, [
            'type' => 'text',
            'name' => 'Text block',
            'content' => ['richText' => '<p>Updated rich text</p>'],
            'settings' => ['editor' => 'tiptap'],
            'isEnabled' => true,
            'visibility' => 'public',
        ]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Updated rich text', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/api/content/pages/'.$pageId.'/preview-link');
        self::assertResponseIsSuccessful();
        $previewPayload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($previewPayload);
        self::assertIsString($previewPayload['previewUrl'] ?? null);
        self::assertStringContainsString('/_preview/', $previewPayload['previewUrl']);
    }

    private function createAdminUser(string $email, array $roles): AdminUser
    {
        $entityManager = $this->entityManager();
        $user = new AdminUser($email, 'hash', $roles);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequestWithCsrf(KernelBrowser $client, string $method, string $uri, array $payload = []): void
    {
        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', $html, $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        $client->jsonRequest($method, $uri, $payload, [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $matches[1],
            'HTTP_ORIGIN' => 'http://zaborprofil.test',
            'HTTP_HOST' => 'zaborprofil.test',
        ]);
    }

    private function stringFromResponse(string $json, string $key): string
    {
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($payload) || !\is_string($payload[$key] ?? null)) {
            throw new LogicException(\sprintf('Response key "%s" is missing or is not a string.', $key));
        }

        return $payload[$key];
    }
}
