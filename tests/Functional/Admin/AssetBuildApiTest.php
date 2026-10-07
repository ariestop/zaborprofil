<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Tests\Support\Admin\AdminApiTestCase;

final class AssetBuildApiTest extends AdminApiTestCase
{
    public function testBuildFromWebAdminIsDisabledOutsideDev(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'GET', '/admin/api/system/assets/build');
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json($client)['enabled'] ?? null);

        $this->api($client, 'POST', '/admin/api/system/assets/build/run', ['targets' => ['all']]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('ASSET_BUILD_DISABLED', $this->json($client)['code'] ?? null);
    }
}
