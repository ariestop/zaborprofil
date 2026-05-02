<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminSpaShellTest extends WebTestCase
{
    public function testAdminSpaRouteRendersShellForNestedAdminPage(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/settings');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app');
        self::assertSelectorExists('meta[name="admin-csrf-token"]');
    }

    private function createAdminUser(): AdminUser
    {
        $entityManager = $this->entityManager();
        $user = new AdminUser('spa@example.test', 'hash', ['ROLE_ADMIN']);
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
}
