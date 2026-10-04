<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Shared\Infrastructure\Maintenance\MaintenanceState;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Матрица «роль × эндпоинт» для Admin API.
 *
 * Ожидаемые права записаны в тесте независимо от AdminPermissionVoter: изменение
 * прав роли должно быть осознанным и сопровождаться правкой этой таблицы.
 *
 * EDITOR/SEO/MANAGER входят в админку (пункт A2), поэтому матрица проверяет для них
 * права на уровне эндпоинтов наравне с ADMIN и SUPER_ADMIN. Если роль всё же не пустят
 * в оболочку админки, её запросы будут ожидаемо отклоняться везде и тест это покажет.
 */
final class AdminRoleMatrixTest extends WebTestCase
{
    private const string EMAIL_TEMPLATE = 'matrix-%s@example.test';

    private const string UNKNOWN_ID = '01J00000000000000000000000';

    private const array STAFF_ROLES = ['ROLE_EDITOR', 'ROLE_SEO', 'ROLE_MANAGER'];

    private const array ADMINISTRATIVE_ROLES = ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];

    /**
     * Права по ролям: ключ — разрешение, значение — роли, которым оно выдано.
     *
     * @var array<string, list<string>>
     */
    private const array GRANTS = [
        'pages.view' => ['ROLE_EDITOR', 'ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'pages.create' => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'pages.edit' => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'pages.review' => ['ROLE_EDITOR', 'ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'pages.approve' => ['ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'pages.publish' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'seo.edit' => ['ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'media.upload' => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'media.delete' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'leads.work' => ['ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'leads.export' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'catalog.view' => ['ROLE_EDITOR', 'ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'admin.only' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        'super.only' => ['ROLE_SUPER_ADMIN'],
        'any.admitted' => ['ROLE_EDITOR', 'ROLE_SEO', 'ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
    ];

    /**
     * Эндпоинт: [метод, URI, тело, разрешение, безопасно ли выполнять для разрешённых ролей].
     * Деструктивные операции (очистка кэша, перезапуск процессов, миграции) проверяются
     * только на отказ: разрешённым ролям запрос не отправляется.
     *
     * @return list<array{0: string, 1: string, 2: array<string, mixed>, 3: string, 4: bool}>
     */
    private static function endpoints(): array
    {
        $id = self::UNKNOWN_ID;

        return [
            ['GET', '/admin/api/content/pages', [], 'pages.view', true],
            ['GET', '/admin/api/content/templates', [], 'pages.view', true],
            ['GET', '/admin/api/content/block-schemas', [], 'pages.view', true],
            ['GET', "/admin/api/content/pages/{$id}", [], 'pages.view', true],
            ['GET', "/admin/api/content/pages/{$id}/workflow", [], 'pages.view', true],
            ['GET', "/admin/api/content/pages/{$id}/revisions", [], 'pages.view', true],
            ['POST', '/admin/api/content/pages', [], 'pages.edit', true],
            ['PUT', "/admin/api/content/pages/{$id}", [], 'pages.edit', true],
            ['POST', "/admin/api/content/pages/{$id}/duplicate", [], 'pages.create', true],
            ['POST', '/admin/api/content/pages/bulk', ['ids' => [$id], 'action' => 'status', 'status' => 'draft'], 'pages.review', true],
            ['POST', '/admin/api/content/pages/bulk', ['ids' => [$id], 'action' => 'indexable', 'indexable' => false], 'seo.edit', true],
            ['POST', '/admin/api/content/templates', [], 'admin.only', true],
            ['DELETE', '/admin/api/content/templates/matrix-unknown-template', [], 'admin.only', true],
            ['POST', "/admin/api/content/pages/{$id}/blocks", [], 'pages.edit', true],
            ['PUT', "/admin/api/content/pages/{$id}/builder", [], 'pages.edit', true],
            ['POST', "/admin/api/content/pages/{$id}/edit-lock", ['sessionId' => 'matrix-session-1'], 'pages.edit', true],
            ['DELETE', "/admin/api/content/pages/{$id}/edit-lock", ['sessionId' => 'matrix-session-1'], 'pages.edit', true],
            ['PATCH', "/admin/api/content/pages/{$id}/status", ['status' => 'review'], 'pages.review', true],
            ['PATCH', "/admin/api/content/pages/{$id}/status", ['status' => 'approved'], 'pages.approve', true],
            ['PATCH', "/admin/api/content/pages/{$id}/status", ['status' => 'published'], 'pages.publish', true],
            ['POST', "/admin/api/content/pages/{$id}/publish", [], 'pages.publish', true],
            ['POST', "/admin/api/content/pages/{$id}/schedule", [], 'pages.publish', true],
            ['POST', "/admin/api/content/pages/{$id}/archive", [], 'pages.publish', true],
            ['DELETE', "/admin/api/content/blocks/{$id}", [], 'pages.publish', true],
            ['POST', "/admin/api/content/pages/{$id}/revisions/{$id}/rollback", [], 'pages.publish', true],
            ['PUT', "/admin/api/content/pages/{$id}/seo", [], 'seo.edit', true],
            ['GET', '/admin/api/seo/redirects', [], 'seo.edit', true],
            ['POST', '/admin/api/seo/redirects', [], 'seo.edit', true],
            ['GET', '/admin/api/seo/robots', [], 'seo.edit', true],
            ['GET', '/admin/api/seo/not-found', [], 'seo.edit', true],
            ['GET', "/admin/api/seo/audit/pages/{$id}", [], 'seo.edit', true],
            ['GET', '/admin/api/menu/items', [], 'seo.edit', true],
            ['GET', '/admin/api/media/assets', [], 'media.upload', true],
            ['GET', '/admin/api/media/folders', [], 'media.upload', true],
            ['POST', '/admin/api/media/assets', [], 'media.upload', true],
            ['PATCH', "/admin/api/media/assets/{$id}", [], 'media.upload', true],
            ['DELETE', "/admin/api/media/assets/{$id}", [], 'media.delete', true],
            ['GET', '/admin/api/leads', [], 'leads.work', true],
            ['GET', '/admin/api/leads/summary', [], 'leads.work', true],
            ['GET', '/admin/api/leads/assignees', [], 'leads.work', true],
            ['GET', "/admin/api/leads/{$id}", [], 'leads.work', true],
            ['PATCH', "/admin/api/leads/{$id}/status", ['status' => 'in_progress'], 'leads.work', true],
            ['PATCH', "/admin/api/leads/{$id}/assignee", [], 'leads.work', true],
            ['POST', "/admin/api/leads/{$id}/notes", [], 'leads.work', true],
            ['GET', '/admin/api/leads/export', [], 'leads.export', true],
            ['GET', '/admin/api/catalog/categories', [], 'catalog.view', true],
            ['GET', '/admin/api/catalog/products', [], 'catalog.view', true],
            ['POST', '/admin/api/catalog/categories', [], 'admin.only', true],
            ['DELETE', "/admin/api/catalog/products/{$id}", [], 'admin.only', true],
            ['GET', '/admin/api/settings', [], 'admin.only', true],
            ['PUT', '/admin/api/settings/site/matrix_probe', [], 'admin.only', true],
            ['GET', '/admin/api/users', [], 'admin.only', true],
            ['POST', '/admin/api/users', [], 'admin.only', true],
            ['PATCH', "/admin/api/users/{$id}/roles", [], 'admin.only', true],
            ['DELETE', "/admin/api/users/{$id}", [], 'admin.only', true],
            ['GET', '/admin/api/system/health', [], 'admin.only', true],
            ['GET', '/admin/api/system/overview', [], 'admin.only', true],
            ['GET', '/admin/api/system/audit', [], 'admin.only', true],
            ['GET', '/admin/api/system/logs', [], 'admin.only', true],
            ['GET', '/admin/api/system/cache', [], 'admin.only', true],
            ['GET', '/admin/api/system/queues', [], 'admin.only', true],
            ['GET', '/admin/api/system/maintenance', [], 'admin.only', true],
            ['POST', '/admin/api/system/maintenance/on', [], 'admin.only', false],
            ['POST', '/admin/api/system/cache/clear', [], 'super.only', false],
            ['POST', '/admin/api/system/queues/retry-failed', [], 'super.only', false],
            ['POST', '/admin/api/system/queues/remove-failed', [], 'super.only', false],
            ['POST', '/admin/api/system/processes/restart', [], 'super.only', false],
            ['POST', '/admin/api/system/processes/reload', [], 'super.only', false],
            ['POST', '/admin/api/system/security/confirm-token', [], 'super.only', false],
            ['POST', '/admin/api/system/assets/build/run', [], 'admin.only', false],
            ['POST', '/admin/api/settings/migrations/Version20250101000000/apply', [], 'super.only', false],
            ['POST', '/admin/api/users/me/password', [], 'any.admitted', true],
            ['GET', '/admin/api/me', [], 'any.admitted', true],
        ];
    }

    protected function tearDown(): void
    {
        $maintenance = self::getContainer()->get(MaintenanceState::class);
        if ($maintenance instanceof MaintenanceState) {
            $maintenance->disable();
        }

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function roles(): iterable
    {
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_SEO', 'ROLE_MANAGER'] as $role) {
            yield $role => [$role];
        }
    }

    #[DataProvider('roles')]
    public function testRoleSeesOnlyEndpointsItsPermissionsAllow(string $role): void
    {
        $client = self::createClient();
        $users = $this->seedUsers();
        $token = $this->csrfToken($client, $users['ROLE_SUPER_ADMIN']);

        $client->loginUser($users[$role]);
        $admitted = $this->isAdmittedToAdminShell($client);

        $mismatches = [];
        foreach (self::endpoints() as [$method, $uri, $payload, $permission, $executable]) {
            $shouldBeAllowed = $admitted && \in_array($role, self::GRANTS[$permission], true);
            if ($shouldBeAllowed && !$executable) {
                continue;
            }

            $status = $this->send($client, $method, $uri, $payload, $token);
            $label = \sprintf('%s %s [%s]', $method, $uri, $permission);

            if ($shouldBeAllowed) {
                if (\in_array($status, [401, 403], true) || $status >= 500) {
                    $mismatches[] = \sprintf('%s: ожидался доступ, получен %d', $label, $status);
                }

                continue;
            }

            if (403 !== $status) {
                $mismatches[] = \sprintf('%s: ожидался отказ 403, получен %d', $label, $status);
            }
        }

        self::assertSame([], $mismatches, \sprintf("Роль %s: расхождения с матрицей прав.\n%s", $role, implode("\n", $mismatches)));
    }

    public function testAdministrativeRolesAreAdmittedToAdminShell(): void
    {
        $client = self::createClient();
        $users = $this->seedUsers();

        foreach (self::ADMINISTRATIVE_ROLES as $role) {
            $client->loginUser($users[$role]);
            self::assertTrue($this->isAdmittedToAdminShell($client), $role.' должна открывать /admin/dashboard.');
        }
    }

    public function testStaffRolesAreNotEscalatedToAdministrator(): void
    {
        self::createClient();
        $users = $this->seedUsers();

        foreach (self::STAFF_ROLES as $role) {
            self::assertNotContains('ROLE_ADMIN', $users[$role]->getRoles(), $role.' не должна получать ROLE_ADMIN.');
        }
    }

    public function testStaffRolesAreAdmittedToAdminShell(): void
    {
        $client = self::createClient();
        $users = $this->seedUsers();

        $notAdmitted = [];
        foreach (self::STAFF_ROLES as $role) {
            $client->loginUser($users[$role]);
            if (!$this->isAdmittedToAdminShell($client)) {
                $notAdmitted[] = $role;
            }
        }

        self::assertSame([], $notAdmitted, 'Роли не пускают в админку: '.implode(', ', $notAdmitted));
    }

    public function testAnonymousVisitorIsRejectedOnEveryAdminRoute(): void
    {
        $client = self::createClient();

        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        $checked = 0;
        $leaks = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();
            if (!str_starts_with($path, '/admin') || \in_array($name, ['admin_login', 'admin_logout'], true)) {
                continue;
            }

            $uri = (string) preg_replace('/\{[^}]+\}/', 'aaaaaaaaaaaaaaaaaaaaaaaaaa', $path);
            $methods = $route->getMethods();
            $method = [] === $methods ? 'GET' : $methods[0];

            $client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://zaborprofil.test']);
            ++$checked;

            $response = $client->getResponse();
            $status = $response->getStatusCode();
            $redirectsToLogin = \in_array($status, [301, 302, 303, 307], true)
                && str_contains((string) $response->headers->get('Location'), '/admin/login');

            if (!$redirectsToLogin && !\in_array($status, [401, 403], true)) {
                $leaks[] = \sprintf('%s %s (%s) -> %d', $method, $uri, $name, $status);
            }
        }

        self::assertGreaterThan(50, $checked, 'Список маршрутов админки неожиданно мал.');
        self::assertSame([], $leaks, "Анонимный доступ к админке:\n".implode("\n", $leaks));
    }

    /**
     * @return array<string, AdminUser>
     */
    private function seedUsers(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        SchemaTestHelper::recreateSchema($entityManager);

        $users = [];
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_SEO', 'ROLE_MANAGER'] as $role) {
            $user = new AdminUser(\sprintf(self::EMAIL_TEMPLATE, strtolower($role)), 'hash', [$role]);
            $entityManager->persist($user);
            $users[$role] = $user;
        }
        $entityManager->flush();

        return $users;
    }

    private function csrfToken(KernelBrowser $client, AdminUser $superAdmin): string
    {
        $client->loginUser($superAdmin);
        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();

        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', (string) $client->getResponse()->getContent(), $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        return $matches[1];
    }

    private function isAdmittedToAdminShell(KernelBrowser $client): bool
    {
        $client->request('GET', '/admin/dashboard');

        return 200 === $client->getResponse()->getStatusCode();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(KernelBrowser $client, string $method, string $uri, array $payload, string $csrfToken): int
    {
        if ('GET' === $method) {
            $client->request('GET', $uri);

            return $client->getResponse()->getStatusCode();
        }

        $client->jsonRequest($method, $uri, $payload, [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $csrfToken,
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ]);

        return $client->getResponse()->getStatusCode();
    }
}
