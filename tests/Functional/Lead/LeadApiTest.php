<?php

declare(strict_types=1);

namespace App\Tests\Functional\Lead;

use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LeadApiTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testPublicFormCreatesLeadWithConsentSnapshot(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $client->jsonRequest('POST', '/api/leads', [
            'source' => 'public_page_form',
            'name' => 'Иван',
            'phone' => '+79990000000',
            'email' => 'ivan@example.test',
            'message' => 'Нужен расчёт забора',
            'consent' => true,
            'consentText' => 'Согласен на обработку персональных данных.',
            'pageUrl' => 'https://zaborprofil.test/catalog/',
            'policyUrl' => '/privacy/',
            'formLoadedAt' => (new \DateTimeImmutable('-10 seconds'))->format(DATE_ATOM),
        ], server: ['REMOTE_ADDR' => '127.0.0.10']);

        self::assertResponseStatusCodeSame(201);

        $lead = $this->leads()->findLatest(1)[0] ?? null;
        self::assertNotNull($lead);
        $payload = $lead->toArray();
        self::assertSame('new', $payload['status']);
        $consentSnapshot = $payload['consentSnapshot'] ?? null;
        self::assertIsArray($consentSnapshot);
        self::assertSame('https://zaborprofil.test/catalog/', $consentSnapshot['pageUrl'] ?? null);
    }

    public function testSpamLeadIsAcceptedAndStoredAsSpam(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $client->jsonRequest('POST', '/api/leads', [
            'source' => 'public_page_form',
            'name' => 'Spam',
            'phone' => '+79990000000',
            'consent' => true,
            'website' => 'https://spam.example',
            'formLoadedAt' => (new \DateTimeImmutable('-10 seconds'))->format(DATE_ATOM),
        ], server: ['REMOTE_ADDR' => '127.0.0.11']);

        self::assertResponseStatusCodeSame(202);

        $lead = $this->leads()->findLatest(1)[0] ?? null;
        self::assertNotNull($lead);
        $payload = $lead->toArray();
        self::assertSame('spam', $payload['status']);
        $spamReasons = $payload['spamReasons'] ?? null;
        self::assertIsArray($spamReasons);
        self::assertContains('honeypot_filled', $spamReasons);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }

    private function leads(): LeadRepositoryInterface
    {
        $leads = self::getContainer()->get(LeadRepositoryInterface::class);

        if (!$leads instanceof LeadRepositoryInterface) {
            throw new LogicException('Lead repository service is not available.');
        }

        return $leads;
    }
}
