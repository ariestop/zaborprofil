<?php

declare(strict_types=1);

namespace App\Tests\Functional\Console;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCommandsTest extends KernelTestCase
{
    private const string PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        self::bootKernel();
        SchemaTestHelper::recreateSchema($this->entityManager());
        putenv('ADMIN_PASSWORD');
    }

    protected function tearDown(): void
    {
        putenv('ADMIN_PASSWORD');
        parent::tearDown();
    }

    public function testCreateAdminCreatesUserWithAdminRoleAndHashedPassword(): void
    {
        $tester = $this->tester('app:user:create-admin');
        $exitCode = $tester->execute(['email' => 'Owner@Example.test', '--password' => self::PASSWORD], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('создан', $tester->getDisplay());

        $user = $this->findUser('owner@example.test');
        self::assertSame(['ROLE_ADMIN'], $user->storedRoles());
        self::assertTrue($user->isActive());
        self::assertNotSame(self::PASSWORD, $user->getPassword());
        self::assertTrue($this->hasher()->isPasswordValid($user, self::PASSWORD));
    }

    public function testCreateAdminIsIdempotentAndKeepsExistingPassword(): void
    {
        $this->tester('app:user:create-admin')->execute(['email' => 'owner@example.test', '--password' => self::PASSWORD], ['interactive' => false]);
        $hashBefore = $this->findUser('owner@example.test')->getPassword();

        $tester = $this->tester('app:user:create-admin');
        $exitCode = $tester->execute(['email' => 'owner@example.test'], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('изменений нет', $tester->getDisplay());
        self::assertSame($hashBefore, $this->findUser('owner@example.test')->getPassword());

        $tester = $this->tester('app:user:create-admin');
        $tester->execute(['email' => 'owner@example.test', '--password' => 'another-long-password'], ['interactive' => false]);
        self::assertSame($hashBefore, $this->findUser('owner@example.test')->getPassword());
        self::assertSame(1, $this->entityManager()->getRepository(AdminUser::class)->count([]));
    }

    public function testCreateAdminDoesNotTouchExistingAdministratorRoles(): void
    {
        $existing = new AdminUser('seolool@example.test', 'legacy-hash', ['ROLE_ADMIN']);
        $this->entityManager()->persist($existing);
        $this->entityManager()->flush();

        $tester = $this->tester('app:user:create-admin');
        $tester->execute(['email' => 'seolool@example.test'], ['interactive' => false]);

        $user = $this->findUser('seolool@example.test');
        self::assertSame('legacy-hash', $user->getPassword());
        self::assertSame(['ROLE_ADMIN'], $user->storedRoles());
    }

    public function testCreateAdminResetPasswordChangesPasswordAndReactivatesUser(): void
    {
        $tester = $this->tester('app:user:create-admin');
        $tester->execute(['email' => 'owner@example.test', '--password' => self::PASSWORD], ['interactive' => false]);
        $user = $this->findUser('owner@example.test');
        $user->deactivate();
        $this->entityManager()->flush();

        $tester = $this->tester('app:user:create-admin');
        $exitCode = $tester->execute(
            ['email' => 'owner@example.test', '--password' => 'brand-new-password', '--reset-password' => true],
            ['interactive' => false],
        );

        self::assertSame(Command::SUCCESS, $exitCode);
        $this->entityManager()->clear();
        $user = $this->findUser('owner@example.test');
        self::assertTrue($user->isActive());
        self::assertTrue($this->hasher()->isPasswordValid($user, 'brand-new-password'));
        self::assertFalse($this->hasher()->isPasswordValid($user, self::PASSWORD));
    }

    public function testCreateAdminSuperOptionGrantsSuperAdminRole(): void
    {
        $tester = $this->tester('app:user:create-admin');
        $tester->execute(['email' => 'root@example.test', '--password' => self::PASSWORD, '--super' => true], ['interactive' => false]);

        self::assertSame(['ROLE_SUPER_ADMIN'], $this->findUser('root@example.test')->storedRoles());
    }

    public function testCreateAdminReadsPasswordFromEnvironment(): void
    {
        putenv('ADMIN_PASSWORD='.self::PASSWORD);

        $tester = $this->tester('app:user:create-admin');
        $exitCode = $tester->execute(['email' => 'env@example.test'], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertTrue($this->hasher()->isPasswordValid($this->findUser('env@example.test'), self::PASSWORD));
    }

    public function testCreateAdminAsksPasswordInteractively(): void
    {
        $tester = $this->tester('app:user:create-admin');
        $tester->setInputs([self::PASSWORD, self::PASSWORD]);
        $exitCode = $tester->execute(['email' => 'interactive@example.test']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertTrue($this->hasher()->isPasswordValid($this->findUser('interactive@example.test'), self::PASSWORD));
    }

    public function testCreateAdminFailsWithoutPasswordInNonInteractiveMode(): void
    {
        $tester = $this->tester('app:user:create-admin');
        $exitCode = $tester->execute(['email' => 'nopass@example.test'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertNull($this->entityManager()->getRepository(AdminUser::class)->findOneBy(['email' => 'nopass@example.test']));
    }

    public function testCreateAdminRejectsShortPasswordAndInvalidEmail(): void
    {
        $tester = $this->tester('app:user:create-admin');
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'short@example.test', '--password' => 'short'], ['interactive' => false]));
        self::assertStringContainsString('не менее 12', $tester->getDisplay());

        $tester = $this->tester('app:user:create-admin');
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'not-an-email', '--password' => self::PASSWORD], ['interactive' => false]));
        self::assertSame(0, $this->entityManager()->getRepository(AdminUser::class)->count([]));
    }

    public function testChangePasswordCommandUpdatesPassword(): void
    {
        $this->tester('app:user:create-admin')->execute(['email' => 'owner@example.test', '--password' => self::PASSWORD], ['interactive' => false]);

        $tester = $this->tester('app:user:change-password');
        $exitCode = $tester->execute(['email' => 'owner@example.test', '--password' => 'rotated-password-1'], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $this->entityManager()->clear();
        self::assertTrue($this->hasher()->isPasswordValid($this->findUser('owner@example.test'), 'rotated-password-1'));

        $tester = $this->tester('app:user:change-password');
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'missing@example.test', '--password' => 'rotated-password-1'], ['interactive' => false]));
    }

    public function testCommandsWriteAuditEntriesWithoutPasswordHash(): void
    {
        $this->tester('app:user:create-admin')->execute(['email' => 'owner@example.test', '--password' => self::PASSWORD], ['interactive' => false]);
        $this->tester('app:user:change-password')->execute(['email' => 'owner@example.test', '--password' => 'rotated-password-1'], ['interactive' => false]);

        $entries = $this->entityManager()->getRepository(AuditLogEntry::class)->findBy(['entityType' => AdminUser::class], ['occurredAt' => 'ASC']);
        self::assertCount(2, $entries);
        self::assertSame('create', $entries[0]->action());
        self::assertSame('console', $entries[0]->actorEmail());
        self::assertSame('owner@example.test', $entries[0]->newValues()['email'] ?? null);

        $update = null;
        foreach ($entries as $entry) {
            if ($entry->action() === 'update') {
                $update = $entry;
            }
        }
        self::assertNotNull($update);
        self::assertSame('[redacted]', $update->newValues()['password'] ?? null);

        $encoded = json_encode(array_map(static fn (AuditLogEntry $entry): array => [$entry->oldValues(), $entry->newValues()], $entries), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('$2y$', $encoded);
        self::assertStringNotContainsString('$argon', $encoded);
        self::assertStringNotContainsString('passwordHash', $encoded);
    }

    private function tester(string $name): CommandTester
    {
        $kernel = self::$kernel;
        if ($kernel === null) {
            throw new LogicException('Kernel is not booted.');
        }

        return new CommandTester((new Application($kernel))->find($name));
    }

    private function findUser(string $email): AdminUser
    {
        $user = $this->entityManager()->getRepository(AdminUser::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(AdminUser::class, $user);

        return $user;
    }

    private function hasher(): UserPasswordHasherInterface
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        return $hasher;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
