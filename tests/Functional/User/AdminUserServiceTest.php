<?php

declare(strict_types=1);

namespace App\Tests\Functional\User;

use App\Module\User\Application\Service\AdminUserService;
use App\Module\User\Domain\Exception\UserManagementException;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminUserServiceTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        SchemaTestHelper::recreateSchema($this->entityManager());
    }

    public function testLastActiveAdministratorCannotBeDemotedDeactivatedOrDeleted(): void
    {
        $staleActor = $this->persistUser('stale@example.test', ['ROLE_ADMIN'], false);
        $onlyAdmin = $this->persistUser('only@example.test', ['ROLE_ADMIN']);
        $editor = $this->persistUser('editor@example.test', ['ROLE_EDITOR']);

        foreach ([
            fn () => $this->service()->updateRoles($staleActor, $onlyAdmin, ['ROLE_EDITOR']),
            fn () => $this->service()->setActive($staleActor, $onlyAdmin, false),
            fn () => $this->service()->delete($staleActor, $onlyAdmin),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected last administrator guard to trigger.');
            } catch (UserManagementException $exception) {
                self::assertSame(UserManagementException::LAST_ADMIN, $exception->errorCode);
            }
        }

        $this->service()->setActive($staleActor, $editor, false);
        self::assertTrue($onlyAdmin->isActive());
        self::assertSame(['ROLE_ADMIN'], $onlyAdmin->storedRoles());
    }

    public function testLastSuperAdministratorCannotBeDowngraded(): void
    {
        $actor = $this->persistUser('super-a@example.test', ['ROLE_SUPER_ADMIN']);
        $other = $this->persistUser('super-b@example.test', ['ROLE_SUPER_ADMIN']);

        $this->service()->updateRoles($actor, $other, ['ROLE_ADMIN']);
        self::assertSame(['ROLE_ADMIN'], $other->storedRoles());

        $stale = $this->persistUser('stale-admin@example.test', ['ROLE_SUPER_ADMIN'], false);
        $this->expectException(UserManagementException::class);
        $this->service()->updateRoles($stale, $actor, ['ROLE_ADMIN']);
    }

    public function testAdministratorCanDeleteOtherAdministratorWhenAnotherRemains(): void
    {
        $actor = $this->persistUser('actor@example.test', ['ROLE_ADMIN']);
        $other = $this->persistUser('other@example.test', ['ROLE_ADMIN']);

        $this->service()->delete($actor, $other);

        self::assertNull($this->entityManager()->getRepository(AdminUser::class)->findOneBy(['email' => 'other@example.test']));
    }

    /**
     * @param list<string> $roles
     */
    private function persistUser(string $email, array $roles, bool $active = true): AdminUser
    {
        $user = new AdminUser($email, 'hash', $roles);
        if (!$active) {
            $user->deactivate();
        }
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    private function service(): AdminUserService
    {
        $service = self::getContainer()->get(AdminUserService::class);
        self::assertInstanceOf(AdminUserService::class, $service);

        return $service;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
