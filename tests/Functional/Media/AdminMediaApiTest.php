<?php

declare(strict_types=1);

namespace App\Tests\Functional\Media;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Module\Menu\Domain\Entity\MenuItem;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AdminMediaApiTest extends WebTestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testListIsPaginatedAndReportsTotals(): void
    {
        $client = $this->adminClient();
        for ($index = 1; $index <= 5; ++$index) {
            $this->seedAsset(\sprintf('photo-%d.jpg', $index), 'image/jpeg', $index * 100);
        }

        $client->request('GET', '/admin/api/media/assets?perPage=2&page=3&sort=name');

        self::assertResponseIsSuccessful();
        $payload = $this->payload($client);
        self::assertSame(['page' => 3, 'perPage' => 2, 'total' => 5, 'totalPages' => 3], $payload['pagination']);
        self::assertSame(['photo-5.jpg'], $this->originalNames($payload));
    }

    public function testSearchTypeFilterAndSort(): void
    {
        $client = $this->adminClient();
        $this->seedAsset('gate.jpg', 'image/jpeg', 300);
        $this->seedAsset('fence_50%.png', 'image/png', 100);
        $this->seedAsset('price-list.pdf', 'application/pdf', 200);
        $this->seedAsset('other.png', 'image/png', 400, 'Забор из профнастила');

        $client->request('GET', '/admin/api/media/assets?q=fence_50%25');
        self::assertSame(['fence_50%.png'], $this->originalNames($this->payload($client)));

        $client->request('GET', '/admin/api/media/assets?q='.rawurlencode('профнастила'));
        self::assertSame(['other.png'], $this->originalNames($this->payload($client)));

        $client->request('GET', '/admin/api/media/assets?type=document');
        self::assertSame(['price-list.pdf'], $this->originalNames($this->payload($client)));

        $client->request('GET', '/admin/api/media/assets?type=image&sort=size');
        self::assertSame(['other.png', 'gate.jpg', 'fence_50%.png'], $this->originalNames($this->payload($client)));

        $client->request('GET', '/admin/api/media/assets?type=image&sort=name');
        self::assertSame(['fence_50%.png', 'gate.jpg', 'other.png'], $this->originalNames($this->payload($client)));
    }

    public function testInvalidListParametersReturnValidationError(): void
    {
        $client = $this->adminClient();

        foreach (['page=0', 'page=abc', 'perPage=101', 'type=video', 'sort=random'] as $query) {
            $client->request('GET', '/admin/api/media/assets?'.$query);

            self::assertResponseStatusCodeSame(422, $query);
            $payload = $this->payload($client);
            self::assertSame('VALIDATION', $payload['code'] ?? null, $query);
            self::assertIsString($payload['error'] ?? null);
        }
    }

    public function testUploadUpdateMetadataSearchAndDelete(): void
    {
        $client = $this->adminClient();

        $this->csrfRequest($client, 'POST', '/admin/api/media/assets', [
            'file' => $this->pngUpload('Забор.png'),
        ]);
        self::assertResponseStatusCodeSame(201);
        $asset = $this->payload($client);
        $id = $asset['id'] ?? null;
        self::assertIsString($id);
        self::assertSame('Забор.png', $asset['originalName'] ?? null);
        self::assertArrayHasKey('alt', $asset);
        self::assertNull($asset['alt']);
        self::assertArrayHasKey('title', $asset);
        self::assertNull($asset['title']);
        $publicPath = $asset['publicPath'] ?? null;
        self::assertIsString($publicPath);
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);
        $storedFile = $projectDir.'/public_html'.$publicPath;
        self::assertFileExists($storedFile);

        $this->csrfRequest($client, 'PATCH', '/admin/api/media/assets/'.$id, [], ['alt' => '  Забор из профнастила  ', 'title' => 'Фото объекта']);
        self::assertResponseIsSuccessful();
        $updated = $this->payload($client);
        self::assertSame('Забор из профнастила', $updated['alt'] ?? null);
        self::assertSame('Фото объекта', $updated['title'] ?? null);

        $client->request('GET', '/admin/api/media/assets?q='.rawurlencode('профнастила'));
        self::assertSame(['Забор.png'], $this->originalNames($this->payload($client)));

        $this->csrfRequest($client, 'PATCH', '/admin/api/media/assets/'.$id, [], ['alt' => '', 'title' => null]);
        self::assertResponseIsSuccessful();
        $cleared = $this->payload($client);
        self::assertArrayHasKey('alt', $cleared);
        self::assertNull($cleared['alt']);
        self::assertNull($cleared['title']);

        $this->csrfRequest($client, 'DELETE', '/admin/api/media/assets/'.$id);
        self::assertResponseStatusCodeSame(204);
        self::assertFileDoesNotExist($storedFile);

        $client->request('GET', '/admin/api/media/assets');
        self::assertSame([], $this->originalNames($this->payload($client)));
    }

    public function testMetadataValidationAndNotFoundUseErrorFormat(): void
    {
        $client = $this->adminClient();
        $asset = $this->seedAsset('photo.jpg', 'image/jpeg', 100);

        $this->csrfRequest($client, 'PATCH', '/admin/api/media/assets/'.$asset->id(), [], ['alt' => str_repeat('а', 256)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->payload($client)['code'] ?? null);

        $this->csrfRequest($client, 'PATCH', '/admin/api/media/assets/'.$asset->id(), [], ['alt' => 123]);
        self::assertResponseStatusCodeSame(422);

        $this->csrfRequest($client, 'PATCH', '/admin/api/media/assets/01ARZ3NDEKTSV4RRFFQ69G5FAV', [], ['alt' => 'x']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('NOT_FOUND', $this->payload($client)['code'] ?? null);

        $this->csrfRequest($client, 'DELETE', '/admin/api/media/assets/01ARZ3NDEKTSV4RRFFQ69G5FAV');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('NOT_FOUND', $this->payload($client)['code'] ?? null);
    }

    public function testUploadErrorsUseErrorFormat(): void
    {
        $client = $this->adminClient();

        $this->csrfRequest($client, 'POST', '/admin/api/media/assets');
        self::assertResponseStatusCodeSame(400);
        self::assertSame('BAD_REQUEST', $this->payload($client)['code'] ?? null);

        $textFile = $this->temporaryFile('text');
        file_put_contents($textFile, 'not an image');
        $this->csrfRequest($client, 'POST', '/admin/api/media/assets', [
            'file' => new UploadedFile($textFile, 'notes.txt', 'text/plain', null, true),
        ]);
        self::assertResponseStatusCodeSame(422);
        $payload = $this->payload($client);
        self::assertSame('VALIDATION', $payload['code'] ?? null);
        self::assertIsString($payload['error'] ?? null);
    }

    public function testUsagesAreFoundInBlocksOgImageAndMenuAndShownInList(): void
    {
        $client = $this->adminClient();
        $used = $this->seedAsset('hero.jpg', 'image/jpeg', 100);
        $unused = $this->seedAsset('free.jpg', 'image/jpeg', 100);
        $pdf = $this->seedAsset('price.pdf', 'application/pdf', 100);

        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');
        $page->updateSeoMetadata(null, null, null, null, 'https://zaborprofil.test/uploads/media/hero.jpg', null, null);
        $this->entityManager()->persist($page);
        $this->entityManager()->persist(new PageBlock($page, BlockType::Hero, 'Первый экран', 0, ['title' => 'Hi', 'image' => '/uploads/media/hero.jpg']));
        $this->entityManager()->persist(new PageBlock($page, BlockType::Hero, 'Текст', 1, ['html' => '<p><img src=\"/uploads/media/other.jpg\"></p>']));
        $this->entityManager()->persist(new MenuItem('footer', 'Прайс', '/uploads/media/price.pdf'));
        $this->entityManager()->flush();

        $client->request('GET', '/admin/api/media/assets/'.$used->id().'/usages');
        self::assertResponseIsSuccessful();
        $payload = $this->payload($client);
        self::assertSame(2, $payload['total']);
        $usages = $payload['usages'];
        self::assertIsArray($usages);
        $locations = array_map(static fn (mixed $usage): string => \is_array($usage) && \is_string($usage['location'] ?? null) ? $usage['location'] : '', $usages);
        sort($locations);
        self::assertSame(['OG-изображение', 'Блок «Первый экран» (hero)'], $locations);
        $firstUsage = reset($usages);
        self::assertIsArray($firstUsage);
        self::assertSame('Заборы', $firstUsage['title'] ?? null);
        self::assertIsString($firstUsage['adminPath'] ?? null);
        self::assertStringStartsWith('/admin/pages/', $firstUsage['adminPath']);

        $client->request('GET', '/admin/api/media/assets/'.$unused->id().'/usages');
        $emptyUsages = $this->payload($client);
        self::assertSame(0, $emptyUsages['total'] ?? null);
        self::assertSame([], $emptyUsages['usages'] ?? null);

        $client->request('GET', '/admin/api/media/assets/'.$pdf->id().'/usages');
        self::assertSame(1, $this->payload($client)['total'] ?? null);

        $client->request('GET', '/admin/api/media/assets?sort=name');
        $counts = $this->usageCounts($this->payload($client));
        self::assertSame(['free.jpg' => 0, 'hero.jpg' => 2, 'price.pdf' => 1], $counts);

        $client->request('GET', '/admin/api/media/assets?usage=used&sort=name');
        self::assertSame(['hero.jpg', 'price.pdf'], $this->originalNames($this->payload($client)));

        $client->request('GET', '/admin/api/media/assets?usage=unused');
        self::assertSame(['free.jpg'], $this->originalNames($this->payload($client)));

        $client->request('GET', '/admin/api/media/assets/01ARZ3NDEKTSV4RRFFQ69G5FAV/usages');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('NOT_FOUND', $this->payload($client)['code'] ?? null);
    }

    public function testDeletingUsedAssetIsBlockedUnlessForced(): void
    {
        $client = $this->adminClient();
        $asset = $this->seedAsset('hero.jpg', 'image/jpeg', 100);
        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');
        $this->entityManager()->persist($page);
        $this->entityManager()->persist(new PageBlock($page, BlockType::Hero, 'Первый экран', 0, ['image' => '/uploads/media/hero.jpg']));
        $this->entityManager()->flush();

        $this->csrfRequest($client, 'DELETE', '/admin/api/media/assets/'.$asset->id());
        self::assertResponseStatusCodeSame(409);
        $payload = $this->payload($client);
        self::assertSame('MEDIA_IN_USE', $payload['code'] ?? null);
        self::assertIsString($payload['error'] ?? null);
        self::assertSame(1, $payload['total'] ?? null);
        self::assertIsArray($payload['usages'] ?? null);

        $client->request('GET', '/admin/api/media/assets');
        self::assertSame(['hero.jpg'], $this->originalNames($this->payload($client)));

        $this->csrfRequest($client, 'DELETE', '/admin/api/media/assets/'.$asset->id().'?force=1');
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/admin/api/media/assets');
        self::assertSame([], $this->originalNames($this->payload($client)));
    }

    public function testDeletingUnusedAssetDoesNotNeedForce(): void
    {
        $client = $this->adminClient();
        $asset = $this->seedAsset('free.jpg', 'image/jpeg', 100);

        $this->csrfRequest($client, 'DELETE', '/admin/api/media/assets/'.$asset->id());

        self::assertResponseStatusCodeSame(204);
    }

    public function testUploadingSameFileTwiceReturnsExistingAsset(): void
    {
        $client = $this->adminClient();

        $this->csrfRequest($client, 'POST', '/admin/api/media/assets', ['file' => $this->pngUpload('first.png')], null, ['folder' => 'Заборы']);
        self::assertResponseStatusCodeSame(201);
        $first = $this->payload($client);
        self::assertFalse($first['duplicate']);
        self::assertSame('Заборы', $first['folder']);
        self::assertIsString($first['fileHash'] ?? null);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['fileHash']);

        $this->csrfRequest($client, 'POST', '/admin/api/media/assets', ['file' => $this->pngUpload('second.png')]);
        self::assertResponseStatusCodeSame(200);
        $second = $this->payload($client);
        self::assertTrue($second['duplicate']);
        self::assertSame($first['id'], $second['id']);
        self::assertSame('first.png', $second['originalName']);

        $client->request('GET', '/admin/api/media/assets');
        self::assertSame(['first.png'], $this->originalNames($this->payload($client)));

        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);
        self::assertIsString($first['publicPath'] ?? null);
        $storedFile = $projectDir.'/public_html'.$first['publicPath'];
        self::assertFileExists($storedFile);

        self::assertIsString($first['id'] ?? null);
        $this->csrfRequest($client, 'DELETE', '/admin/api/media/assets/'.$first['id']);
        self::assertResponseStatusCodeSame(204);
        self::assertFileDoesNotExist($storedFile);
    }

    public function testExtendedMetadataFoldersAndPartialPatch(): void
    {
        $client = $this->adminClient();
        $asset = $this->seedAsset('photo.jpg', 'image/jpeg', 100, 'Старый alt');

        $this->csrfRequest($client, 'PATCH', '/admin/api/media/assets/'.$asset->id(), [], ['description' => '  Объект на Ленина  ', 'folder' => 'Заборы']);
        self::assertResponseIsSuccessful();
        $updated = $this->payload($client);
        self::assertSame('Объект на Ленина', $updated['description']);
        self::assertSame('Заборы', $updated['folder']);
        self::assertSame('Старый alt', $updated['alt']);

        $this->csrfRequest($client, 'PATCH', '/admin/api/media/assets/'.$asset->id(), [], ['folder' => 'a/b']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->payload($client)['code'] ?? null);

        $this->csrfRequest($client, 'PATCH', '/admin/api/media/assets/'.$asset->id(), [], ['description' => str_repeat('а', 2001)]);
        self::assertResponseStatusCodeSame(422);

        $this->seedAsset('other.jpg', 'image/jpeg', 100);

        $client->request('GET', '/admin/api/media/folders');
        self::assertResponseIsSuccessful();
        self::assertSame(['folders' => [['name' => 'Заборы', 'count' => 1]]], $this->payload($client));

        $client->request('GET', '/admin/api/media/assets?folder='.rawurlencode('Заборы'));
        self::assertSame(['photo.jpg'], $this->originalNames($this->payload($client)));

        $client->request('GET', '/admin/api/media/assets?folder=__none__');
        self::assertSame(['other.jpg'], $this->originalNames($this->payload($client)));

        $client->request('GET', '/admin/api/media/assets?q='.rawurlencode('Ленина'));
        self::assertSame(['photo.jpg'], $this->originalNames($this->payload($client)));
    }

    public function testFormatDateAndSizeFilters(): void
    {
        $client = $this->adminClient();
        $this->seedAsset('a.jpg', 'image/jpeg', 300);
        $this->seedAsset('b.png', 'image/png', 100);
        $this->seedAsset('c.webp', 'image/webp', 200);

        $client->request('GET', '/admin/api/media/assets?format=png');
        self::assertSame(['b.png'], $this->originalNames($this->payload($client)));

        $client->request('GET', '/admin/api/media/assets?sort=size_asc');
        self::assertSame(['b.png', 'c.webp', 'a.jpg'], $this->originalNames($this->payload($client)));

        $today = (new DateTimeImmutable())->format('Y-m-d');
        $client->request('GET', '/admin/api/media/assets?from='.$today.'&to='.$today);
        $todayNames = $this->originalNames($this->payload($client));
        sort($todayNames);
        self::assertSame(['a.jpg', 'b.png', 'c.webp'], $todayNames);

        $client->request('GET', '/admin/api/media/assets?to=2000-01-01');
        self::assertSame([], $this->originalNames($this->payload($client)));

        foreach (['format=gif', 'usage=maybe', 'from=yesterday', 'from=2026-02-01&to=2026-01-01', 'sort=size_desc'] as $query) {
            $client->request('GET', '/admin/api/media/assets?'.$query);
            self::assertResponseStatusCodeSame(422, $query);
            self::assertSame('VALIDATION', $this->payload($client)['code'] ?? null, $query);
        }
    }

    private function adminClient(): KernelBrowser
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $user = new AdminUser('media@example.test', 'hash', ['ROLE_ADMIN']);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();
        $client->loginUser($user);

        return $client;
    }

    private function seedAsset(string $name, string $mimeType, int $size, ?string $alt = null): MediaAsset
    {
        $asset = new MediaAsset($name, strtolower(str_replace(['%', ' '], '', $name)), '/uploads/media/'.rawurlencode($name), $mimeType, $size, null, null);
        if ($alt !== null) {
            $asset->updateMetadata($alt, null);
        }
        $this->assets()->save($asset);

        return $asset;
    }

    /**
     * @param array<string, UploadedFile> $files
     * @param array<string, mixed>|null   $json
     * @param array<string, string>       $parameters
     */
    private function csrfRequest(KernelBrowser $client, string $method, string $uri, array $files = [], ?array $json = null, array $parameters = []): void
    {
        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', $html, $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        $server = [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $matches[1],
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ];

        if ($json !== null) {
            $client->jsonRequest($method, $uri, $json, $server);

            return;
        }

        $client->request($method, $uri, $parameters, $files, $server);
    }

    private function pngUpload(string $clientName): UploadedFile
    {
        $path = $this->temporaryFile('png');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true));

        return new UploadedFile($path, $clientName, 'image/png', null, true);
    }

    private function temporaryFile(string $suffix): string
    {
        $path = sys_get_temp_dir().'/media-test-'.bin2hex(random_bytes(6)).'.'.$suffix;
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @return array<mixed>
     */
    private function payload(KernelBrowser $client): array
    {
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($payload)) {
            throw new LogicException('Response payload must be a JSON object.');
        }

        return $payload;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return list<string>
     */
    private function originalNames(array $payload): array
    {
        $assets = $payload['assets'] ?? [];
        if (!\is_array($assets)) {
            throw new LogicException('Response must contain assets list.');
        }

        return array_values(array_map(
            static fn (mixed $asset): string => \is_array($asset) && \is_string($asset['originalName'] ?? null) ? $asset['originalName'] : '',
            $assets,
        ));
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<string, int>
     */
    private function usageCounts(array $payload): array
    {
        $counts = [];
        $assets = $payload['assets'] ?? [];
        if (!\is_array($assets)) {
            return [];
        }

        foreach ($assets as $asset) {
            if (\is_array($asset) && \is_string($asset['originalName'] ?? null) && \is_int($asset['usageCount'] ?? null)) {
                $counts[$asset['originalName']] = $asset['usageCount'];
            }
        }

        return $counts;
    }

    private function assets(): MediaAssetRepositoryInterface
    {
        $assets = self::getContainer()->get(MediaAssetRepositoryInterface::class);
        if (!$assets instanceof MediaAssetRepositoryInterface) {
            throw new LogicException('Media asset repository is not available.');
        }

        return $assets;
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
