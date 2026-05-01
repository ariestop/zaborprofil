<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Забор жалюзи',
            'slug' => 'zabor-jaluzi',
            'path' => '/zabor-jaluzi/',
            'h1' => 'Забор жалюзи',
            'template' => 'landing',
        ]);

        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse($client->getResponse()->getContent() ?: '', 'id');

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/blocks', $pageId), [
            'type' => 'hero',
            'name' => 'Главный экран',
            'position' => 0,
            'content' => ['title' => 'Забор жалюзи под ключ', 'text' => 'Производство и монтаж.'],
            'settings' => [],
            'isEnabled' => true,
        ]);

        self::assertResponseStatusCodeSame(201);

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
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

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
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

    public function testAdminApiRejectsRequestWithoutCsrfToken(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('csrf@example.test'));

        $client->jsonRequest('POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'No CSRF',
            'slug' => 'no-csrf',
            'path' => '/no-csrf/',
            'h1' => 'No CSRF',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    private function prepareDatabase(): void
    {
        SchemaTestHelper::recreateSchema($this->entityManager());
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

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequestWithCsrf(KernelBrowser $client, string $method, string $uri, array $payload = []): void
    {
        $tokenManager = self::getContainer()->get('security.csrf.token_manager');

        if (!$tokenManager instanceof CsrfTokenManagerInterface) {
            throw new LogicException('CSRF token manager service is not available.');
        }

        $token = $tokenManager->getToken(AdminApiCsrfSubscriber::TOKEN_ID)->getValue();

        $client->jsonRequest($method, $uri, $payload, [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $token,
        ]);
    }
}
