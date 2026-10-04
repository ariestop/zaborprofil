<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use App\Tests\Support\Admin\AdminApiTestCase;
use LogicException;

final class RedirectAdminApiTest extends AdminApiTestCase
{
    public function testCreateListSearchUpdateAndDelete(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'POST', '/admin/api/seo/redirects', [
            'sourcePath' => '/old-fences/',
            'targetPath' => '/fences/',
            'statusCode' => 301,
            'isActive' => true,
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->json($client);
        self::assertSame('/old-fences/', $created['sourcePath']);
        self::assertIsString($created['id']);
        self::assertIsArray($created['warnings']);

        $this->api($client, 'POST', '/admin/api/seo/redirects', ['sourcePath' => '/gates/', 'targetPath' => '/fences/gates/', 'statusCode' => 302]);
        self::assertResponseStatusCodeSame(201);

        $this->api($client, 'GET', '/admin/api/seo/redirects?q=fences&sort=source&direction=desc&perPage=1');
        self::assertResponseIsSuccessful();
        $list = $this->json($client);
        self::assertSame(2, $list['total']);
        self::assertSame(2, $list['pages']);
        self::assertIsArray($list['items']);
        self::assertCount(1, $list['items']);
        self::assertSame(['total' => 2, 'active' => 2, 'inactive' => 0], $list['counts']);

        $this->api($client, 'PUT', '/admin/api/seo/redirects/'.$created['id'], [
            'sourcePath' => '/old-fences-v2/',
            'targetPath' => '/fences/',
            'statusCode' => 308,
            'isActive' => false,
        ]);
        self::assertResponseIsSuccessful();
        $updated = $this->json($client);
        self::assertSame('/old-fences-v2/', $updated['sourcePath']);
        self::assertSame(308, $updated['statusCode']);
        self::assertFalse($updated['isActive']);

        $this->api($client, 'GET', '/admin/api/seo/redirects?status=inactive');
        self::assertSame(1, $this->json($client)['total']);

        $this->api($client, 'DELETE', '/admin/api/seo/redirects/'.$created['id']);
        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->redirects()->findById((string) $created['id']));

        $this->api($client, 'DELETE', '/admin/api/seo/redirects/'.$created['id']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('NOT_FOUND', $this->json($client)['code']);

        $this->api($client, 'PUT', '/admin/api/seo/redirects/01ARZ3NDEKTSV4RRFFQ69G5FAV', ['targetPath' => '/x/']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('NOT_FOUND', $this->json($client)['code']);
    }

    public function testCyrillicSourceIsStoredPercentEncodedAndMatchesRequest(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'POST', '/admin/api/seo/redirects', ['sourcePath' => '/старые-заборы/', 'targetPath' => '/fences/']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('/%D1%81%D1%82%D0%B0%D1%80%D1%8B%D0%B5-%D0%B7%D0%B0%D0%B1%D0%BE%D1%80%D1%8B/', $this->json($client)['sourcePath']);

        $client->request('GET', '/%D1%81%D1%82%D0%B0%D1%80%D1%8B%D0%B5-%D0%B7%D0%B0%D0%B1%D0%BE%D1%80%D1%8B/');
        self::assertResponseRedirects('/fences/', 301);
    }

    public function testValidationRulesReturnUnifiedErrors(): void
    {
        $client = $this->adminClient();
        $this->redirects()->save(new Redirect('/a/', '/b/'));

        $cases = [
            'self redirect' => [['sourcePath' => '/x/', 'targetPath' => '/x/'], 'targetPath', 'VALIDATION'],
            'admin target' => [['sourcePath' => '/x/', 'targetPath' => '/admin/pages'], 'targetPath', 'VALIDATION'],
            'admin source' => [['sourcePath' => '/admin/x', 'targetPath' => '/y/'], 'sourcePath', 'VALIDATION'],
            'duplicate' => [['sourcePath' => '/a/', 'targetPath' => '/z/'], 'sourcePath', 'REDIRECT_DUPLICATE'],
            'loop' => [['sourcePath' => '/b/', 'targetPath' => '/a/'], 'targetPath', 'REDIRECT_LOOP'],
            'bad status' => [['sourcePath' => '/x/', 'targetPath' => '/y/', 'statusCode' => 200], 'statusCode', 'VALIDATION'],
        ];

        foreach ($cases as $name => [$payload, $field, $code]) {
            $this->api($client, 'POST', '/admin/api/seo/redirects', $payload);
            self::assertResponseStatusCodeSame(422, $name);
            $body = $this->json($client);
            self::assertSame($code, $body['code'], $name);
            self::assertIsString($body['error'], $name);
            self::assertIsArray($body['details'], $name);
            self::assertSame($field, $this->rows($body['details'])[0]['field'] ?? null, $name);
        }

        self::assertSame(1, $this->redirects()->counts()['total']);
    }

    public function testChainProducesWarningAndInactiveRuleSkipsLoopCheck(): void
    {
        $client = $this->adminClient();
        $this->redirects()->save(new Redirect('/b/', '/c/'));

        $this->api($client, 'POST', '/admin/api/seo/redirects', ['sourcePath' => '/a/', 'targetPath' => '/b/']);
        self::assertResponseStatusCodeSame(201);
        $warnings = $this->json($client)['warnings'];
        self::assertIsArray($warnings);
        self::assertContains('chain', array_column($warnings, 'code'));

        $this->api($client, 'POST', '/admin/api/seo/redirects', ['sourcePath' => '/c/', 'targetPath' => '/a/', 'isActive' => false]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testAnalysisFindsLoopsAndChains(): void
    {
        $client = $this->adminClient();
        $repository = $this->redirects();
        $repository->saveAll([
            new Redirect('/loop-a/', '/loop-b/'),
            new Redirect('/loop-b/', '/loop-a/'),
            new Redirect('/h/', '/m/'),
            new Redirect('/m/', '/final/'),
            new Redirect('/disabled/', '/h/', 301, false),
        ]);

        $this->api($client, 'GET', '/admin/api/seo/redirects/analysis');
        self::assertResponseIsSuccessful();
        $analysis = $this->json($client);

        self::assertSame(4, $analysis['activeRules']);
        self::assertIsArray($analysis['loops']);
        self::assertCount(1, $analysis['loops']);
        $chains = $this->rows($analysis['chains']);
        self::assertCount(1, $chains);
        self::assertSame(['/h/', '/m/', '/final/'], $chains[0]['path']);
        self::assertSame('/final/', $chains[0]['finalTarget']);
        self::assertCount(2, $this->rows($chains[0]['rules']));
    }

    public function testImportDryRunThenApplyReportsErrors(): void
    {
        $client = $this->adminClient();
        $this->redirects()->save(new Redirect('/exists/', '/somewhere/'));

        $csv = implode("\n", [
            'source;target;status',
            '/new-one/;/target-one/;301',
            '/exists/;/another/;302',
            '/bad-status/;/t/;200',
            '/self/;/self/',
            '/admin/x;/t/',
            '/new-one/;/dup/',
            '/loop-a/;/loop-b/',
            '/loop-b/;/loop-a/',
            '/кириллица/;/target-cyr/',
        ]);

        $this->api($client, 'POST', '/admin/api/seo/redirects/import', ['csv' => $csv]);
        self::assertResponseIsSuccessful();
        $preview = $this->json($client);
        self::assertTrue($preview['dryRun']);
        self::assertSame(9, $preview['totalRows']);
        self::assertSame(3, $preview['created']);
        self::assertSame(1, $preview['skipped']);
        self::assertSame(5, $preview['failed']);
        self::assertSame(1, $this->redirects()->counts()['total'], 'Dry run must not write.');

        $errors = $this->rows($preview['errors']);
        self::assertSame([4, 5, 6, 7, 9], array_column($errors, 'line'));
        self::assertStringContainsString('цикл', $this->text($errors[4]['message']));

        $this->api($client, 'POST', '/admin/api/seo/redirects/import', ['csv' => $csv, 'dryRun' => false]);
        self::assertResponseIsSuccessful();
        $report = $this->json($client);
        self::assertFalse($report['dryRun']);
        self::assertSame(3, $report['created']);
        self::assertSame(301, $this->redirects()->findBySourcePath('/exists/')?->statusCode(), 'Existing rule is kept when updateExisting is false.');
        self::assertSame(4, $this->redirects()->counts()['total']);
        self::assertNotNull($this->redirects()->findBySourcePath('/new-one/'));
        self::assertNotNull($this->redirects()->findBySourcePath('/кириллица/'));

        $this->api($client, 'POST', '/admin/api/seo/redirects/import', ['csv' => "/exists/,/another/,302\n", 'dryRun' => false, 'updateExisting' => true]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json($client)['updated']);
        self::assertSame(302, $this->redirects()->findBySourcePath('/exists/')?->statusCode());
    }

    public function testImportRejectsEmptyAndMalformedBodies(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'POST', '/admin/api/seo/redirects/import', ['csv' => "\n# nothing\n"]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->json($client)['code']);

        $this->api($client, 'POST', '/admin/api/seo/redirects/import', []);
        self::assertResponseStatusCodeSame(422);
    }

    public function testExportReturnsCsvThatCanBeImportedBack(): void
    {
        $client = $this->adminClient();
        $this->redirects()->saveAll([
            new Redirect('/a/', '/b/', 301),
            new Redirect('/c/', 'https://example.com/d/?x=1,2', 302, false),
        ]);

        $client->request('GET', '/admin/api/seo/redirects/export');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $client->getResponse()->headers->get('Content-Disposition'));
        $csv = (string) $client->getResponse()->getContent();
        self::assertStringStartsWith("source,target,status,active\n", $csv);

        $this->entityManager()->createQuery('DELETE FROM '.Redirect::class.' r')->execute();
        $this->api($client, 'POST', '/admin/api/seo/redirects/import', ['csv' => $csv, 'dryRun' => false]);
        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->json($client)['created']);
        $restored = $this->redirects()->findBySourcePath('/c/');
        self::assertSame('https://example.com/d/?x=1,2', $restored?->targetPath());
        self::assertFalse($restored->isActive());
    }

    public function testDeleteIsAuditedButHitsAreNot(): void
    {
        $client = $this->adminClient();
        $redirect = new Redirect('/old/', '/new/');
        $this->redirects()->save($redirect);

        $client->request('GET', '/old/');
        self::assertResponseRedirects('/new/', 301);
        $client->request('GET', '/old/');

        $this->entityManager()->clear();
        self::assertSame(2, $this->redirects()->findBySourcePath('/old/')?->hitCount());
        self::assertSame([], $this->auditActions('update'));

        $this->api($client, 'DELETE', '/admin/api/seo/redirects/'.$redirect->id());
        self::assertResponseStatusCodeSame(204);
        self::assertCount(1, $this->auditActions('delete'));
    }

    /**
     * @return list<AuditLogEntry>
     */
    private function auditActions(string $action): array
    {
        /** @var list<AuditLogEntry> $entries */
        $entries = $this->entityManager()->getRepository(AuditLogEntry::class)->findBy(['action' => $action, 'entityType' => Redirect::class]);

        return $entries;
    }

    private function redirects(): RedirectRepositoryInterface
    {
        $repository = self::getContainer()->get(RedirectRepositoryInterface::class);
        if (!$repository instanceof RedirectRepositoryInterface) {
            throw new LogicException('Redirect repository is not available.');
        }

        return $repository;
    }
}
