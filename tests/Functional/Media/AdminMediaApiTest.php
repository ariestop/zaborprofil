<?php

declare(strict_types=1);

namespace App\Tests\Functional\Media;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
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
     */
    private function csrfRequest(KernelBrowser $client, string $method, string $uri, array $files = [], ?array $json = null): void
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

        $client->request($method, $uri, [], $files, $server);
    }

    private function pngUpload(string $clientName): UploadedFile
    {
        $path = $this->temporaryFile('png');
        $image = imagecreatetruecolor(40, 30);
        if ($image === false) {
            throw new LogicException('GD cannot create test image.');
        }
        imagepng($image, $path);

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
