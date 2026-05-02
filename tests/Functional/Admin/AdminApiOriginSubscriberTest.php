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

final class AdminApiOriginSubscriberTest extends WebTestCase
{
    public function testAdminApiRejectsStateChangingRequestFromForeignOrigin(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser());

        $token = $this->csrfTokenFromShell($client);
        $client->jsonRequest('PUT', '/admin/api/settings/site/name', ['value' => 'Name'], [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $token,
            'HTTP_ORIGIN' => 'https://evil.example',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminApiAcceptsSameOriginRequest(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('origin-ok@example.test'));

        $token = $this->csrfTokenFromShell($client);
        $client->jsonRequest('PUT', '/admin/api/settings/site/name', ['value' => 'Name'], [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $token,
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ]);

        self::assertResponseIsSuccessful();
    }

    private function csrfTokenFromShell(KernelBrowser $client): string
    {
        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', $html, $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        return $matches[1];
    }

    private function createAdminUser(string $email = 'origin@example.test'): AdminUser
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
}
