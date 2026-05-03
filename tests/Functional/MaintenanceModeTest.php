<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Shared\Infrastructure\Maintenance\MaintenanceState;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MaintenanceModeTest extends WebTestCase
{
    protected function tearDown(): void
    {
        $this->maintenanceState()->disable();
        parent::tearDown();
    }

    public function testMaintenanceModeReturnsServiceUnavailableForPublicPage(): void
    {
        $client = self::createClient();
        $this->maintenanceState()->enable('Технические работы');

        $client->request('GET', '/any-public-page/');

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('h1', 'Техническое обслуживание');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }

    public function testHealthEndpointIsAvailableDuringMaintenance(): void
    {
        $client = self::createClient();
        $this->maintenanceState()->enable('Технические работы');

        $client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
    }

    private function maintenanceState(): MaintenanceState
    {
        $maintenance = self::getContainer()->get(MaintenanceState::class);
        if (!$maintenance instanceof MaintenanceState) {
            throw new LogicException('MaintenanceState service is not available.');
        }

        return $maintenance;
    }
}
