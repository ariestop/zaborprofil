<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminContentApiTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testAdminCanCreatePublishAndRenderPage(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('admin@example.test'));

        $client->jsonRequest('POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Забор жалюзи',
            'slug' => 'zabor-jaluzi',
            'path' => '/zabor-jaluzi/',
            'h1' => 'Забор жалюзи',
            'template' => 'landing',
        ]);

        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse($client->getResponse()->getContent() ?: '', 'id');

        $client->jsonRequest('POST', \sprintf('/admin/api/content/pages/%s/blocks', $pageId), [
            'type' => 'hero',
            'name' => 'Главный экран',
            'position' => 0,
            'content' => ['title' => 'Забор жалюзи под ключ', 'text' => 'Производство и монтаж.'],
            'settings' => [],
            'isEnabled' => true,
        ]);

        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/zabor-jaluzi/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Забор жалюзи');
        self::assertSelectorTextContains('h2', 'Забор жалюзи под ключ');
    }

    public function testDraftPageIsNotPubliclyVisible(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('editor@example.test'));

        $client->jsonRequest('POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Черновик',
            'slug' => 'draft-page',
            'path' => '/draft-page/',
            'h1' => 'Черновик',
        ]);

        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/draft-page/');

        self::assertResponseStatusCodeSame(404);
    }

    private function prepareDatabase(): void
    {
        $entityManager = $this->entityManager();
        $connection = $entityManager->getConnection();

        $connection->executeStatement('DROP TABLE IF EXISTS content_page_blocks');
        $connection->executeStatement('DROP TABLE IF EXISTS content_pages');
        $connection->executeStatement('DROP TABLE IF EXISTS admin_users');
        $connection->executeStatement('CREATE TABLE admin_users (id CHAR(26) NOT NULL PRIMARY KEY, email VARCHAR(180) NOT NULL, active BOOLEAN NOT NULL, roles CLOB NOT NULL, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $connection->executeStatement('CREATE UNIQUE INDEX uniq_admin_users_email ON admin_users (email)');
        $connection->executeStatement('CREATE TABLE content_pages (id CHAR(26) NOT NULL PRIMARY KEY, parent_id CHAR(26) DEFAULT NULL, type VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, h1 VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, template VARCHAR(120) NOT NULL, sort_order INTEGER NOT NULL, indexable BOOLEAN NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL)');
        $connection->executeStatement('CREATE UNIQUE INDEX uniq_content_pages_path ON content_pages (path)');
        $connection->executeStatement('CREATE TABLE content_page_blocks (id CHAR(26) NOT NULL PRIMARY KEY, page_id CHAR(26) NOT NULL, type VARCHAR(64) NOT NULL, name VARCHAR(180) NOT NULL, position INTEGER NOT NULL, is_enabled BOOLEAN NOT NULL, content CLOB NOT NULL, settings CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
    }

    private function createAdminUser(string $email): AdminUser
    {
        $entityManager = $this->entityManager();
        $user = new AdminUser($email, 'hash', ['ROLE_ADMIN']);
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

    private function stringFromResponse(string $content, string $key): string
    {
        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (!\is_array($payload) || !isset($payload[$key]) || !\is_string($payload[$key])) {
            throw new LogicException(\sprintf('Response field "%s" must be a string.', $key));
        }

        return $payload[$key];
    }
}
