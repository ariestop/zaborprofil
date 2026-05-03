<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SystemHealthApiTest extends WebTestCase
{
    public function testAdminCanReadSystemHealth(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/api/system/health');

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('ok', $payload['status']);
        self::assertIsArray($payload['checks'] ?? null);
        self::assertIsArray($payload['environment'] ?? null);
    }

    private function createAdminUser(): AdminUser
    {
        $entityManager = $this->entityManager();
        $user = new AdminUser('health@example.test', 'hash', ['ROLE_ADMIN']);
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
