<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Запрос к Admin API без сессии (истекла, сброшена сменой пароля, выход в другой вкладке) получает 401 JSON,
 * а не редирект на форму входа: fetch следует редиректу и принимает HTML формы входа за успешный ответ.
 * HTML-страницы админки по-прежнему перенаправляют на вход.
 */
final class AdminApiUnauthenticatedTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testAnonymousApiReadReturnsJson401(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $client->request('GET', '/admin/api/content/pages', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertUnauthorizedJson($client);
    }

    public function testAnonymousApiMutationReturnsJson401InsteadOfRedirect(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        // Браузер отправляет Origin своего сайта: проверку Origin запрос проходит, отказ — из-за отсутствия сессии.
        $client->jsonRequest('POST', '/admin/api/content/pages/01JQY3Y5SFSVCE00H9GQWQY56V/publish', [], [
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
            'HTTP_HOST' => 'zaborprofil.test',
            'HTTPS' => 'on',
        ]);

        $this->assertUnauthorizedJson($client);
    }

    public function testApiRequestAfterSessionInvalidationReturnsJson401(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $user = new AdminUser('expired-session@example.test', 'hash', ['ROLE_ADMIN']);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();
        $client->loginUser($user);

        $client->request('GET', '/admin/api/content/pages');
        self::assertResponseIsSuccessful();

        // Смена пароля делает текущую сессию недействительной (как и её истечение).
        $fresh = $this->entityManager()->getRepository(AdminUser::class)->find($user->id());
        self::assertInstanceOf(AdminUser::class, $fresh);
        $fresh->changePasswordHash('rotated-hash');
        $this->entityManager()->flush();

        $client->request('GET', '/admin/api/content/pages');

        $this->assertUnauthorizedJson($client);
    }

    public function testAnonymousAdminPageStillRedirectsToLogin(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $client->request('GET', '/admin/pages');

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/login', (string) $client->getResponse()->headers->get('Location'));
    }

    private function assertUnauthorizedJson(KernelBrowser $client): void
    {
        self::assertResponseStatusCodeSame(401);
        self::assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('UNAUTHORIZED', $payload['code'] ?? null);
        self::assertIsString($payload['error'] ?? null);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }
}
