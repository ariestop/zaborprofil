<?php

declare(strict_types=1);

namespace App\Tests\Functional\Logging;

use App\Shared\Infrastructure\Http\RequestIdSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RequestIdHeaderTest extends WebTestCase
{
    public function testRequestIdHeaderIsGenerated(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertNotSame('', $client->getResponse()->headers->get(RequestIdSubscriber::HEADER));
    }

    public function testIncomingRequestIdIsPreserved(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health', server: [
            'HTTP_X_REQUEST_ID' => 'manual-request-id-123',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame(RequestIdSubscriber::HEADER, 'manual-request-id-123');
    }
}
