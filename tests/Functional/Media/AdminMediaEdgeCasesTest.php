<?php

declare(strict_types=1);

namespace App\Tests\Functional\Media;

use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Tests\Support\Admin\AdminApiTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Пограничные случаи Media API: небезопасные и повреждённые файлы, лимиты, метаданные.
 * Отказ загрузки не должен оставлять файлов в каталоге медиа.
 */
final class AdminMediaEdgeCasesTest extends AdminApiTestCase
{
    private const string ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    private const int MAX_UPLOAD_BYTES = 10_485_760;

    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function rejectedUploads(): iterable
    {
        yield 'php-код под видом png' => ['evil.png', 'image/png', "<?php echo 'pwned';"];
        yield 'текст с расширением jpg' => ['notes.jpg', 'image/jpeg', 'просто текст'];
        yield 'пустой файл' => ['empty.png', 'image/png', ''];
        yield 'svg' => ['logo.svg', 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'];
        yield 'html' => ['page.html', 'text/html', '<html></html>'];
        yield 'исполняемый скрипт' => ['run.sh', 'application/x-sh', "#!/bin/sh\nrm -rf /"];
        yield 'обрезанный png' => ['broken.png', 'image/png', substr((string) base64_decode(self::ONE_PIXEL_PNG, true), 0, 12)];
    }

    #[DataProvider('rejectedUploads')]
    public function testUnsafeOrBrokenFilesAreRejectedWithoutLeavingFiles(string $name, string $clientMime, string $content): void
    {
        $client = $this->adminClient();
        $before = $this->storedFiles();

        $this->upload($client, $this->temporaryFile($content), $name, $clientMime);

        self::assertResponseStatusCodeSame(422, $name);
        $payload = $this->json($client);
        self::assertSame('VALIDATION', $payload['code'] ?? null);
        self::assertSame($before, $this->storedFiles(), 'Отклонённая загрузка не должна оставлять файл.');
        self::assertSame([], $this->assets()->findLatest());
    }

    public function testDoubleExtensionIsRejectedEvenForValidImage(): void
    {
        $client = $this->adminClient();
        $before = $this->storedFiles();

        $this->upload($client, $this->temporaryFile($this->pngBytes()), 'shell.php.png', 'image/png');

        self::assertResponseStatusCodeSame(422);
        self::assertSame($before, $this->storedFiles());
    }

    public function testFileOverSizeLimitIsRejected(): void
    {
        $client = $this->adminClient();
        $before = $this->storedFiles();
        $oversize = $this->pngBytes().str_repeat("\0", self::MAX_UPLOAD_BYTES);

        $this->upload($client, $this->temporaryFile($oversize), 'huge.png', 'image/png');

        self::assertResponseStatusCodeSame(422);
        self::assertContains($this->json($client)['code'] ?? null, ['FILE_TOO_LARGE', 'VALIDATION']);
        self::assertSame($before, $this->storedFiles());
    }

    public function testImageWiderThanLimitIsRejected(): void
    {
        $client = $this->adminClient();
        $before = $this->storedFiles();

        $this->upload($client, $this->temporaryFile($this->pngHeaderOnly(8001, 10)), 'wide.png', 'image/png');

        self::assertResponseStatusCodeSame(422);
        self::assertSame($before, $this->storedFiles());
    }

    public function testTraversalInClientFileNameDoesNotAffectStoredPath(): void
    {
        $client = $this->adminClient();

        $this->upload($client, $this->temporaryFile($this->pngBytes()), '../../../etc/passwd.png', 'image/png');

        self::assertResponseStatusCodeSame(201);
        $asset = $this->json($client);
        $publicPath = $this->text($asset['publicPath'] ?? null);
        self::assertMatchesRegularExpression('#^/uploads/media/[0-9a-z]{26}\.png$#', $publicPath);
        self::assertStringNotContainsString('..', $publicPath);
        self::assertStringNotContainsString('/', $this->text($asset['originalName'] ?? null));

        $this->api($client, 'DELETE', '/admin/api/media/assets/'.$this->text($asset['id'] ?? null));
        self::assertResponseStatusCodeSame(204);
    }

    public function testUploadFieldMustBeAFileNotAString(): void
    {
        $client = $this->adminClient();

        $this->multipart($client, [], ['file' => 'not-a-file']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('BAD_REQUEST', $this->json($client)['code'] ?? null);
    }

    #[DataProvider('invalidFolders')]
    public function testUploadRejectsForbiddenFolderAndKeepsNoFile(string $folder): void
    {
        $client = $this->adminClient();
        $before = $this->storedFiles();

        $this->upload($client, $this->temporaryFile($this->pngBytes()), 'ok.png', 'image/png', ['folder' => $folder]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->assets()->findLatest());
        self::assertSame($before, $this->storedFiles());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidFolders(): iterable
    {
        yield 'слэш' => ['a/b'];
        yield 'обратный слэш' => ['a\\b'];
        yield 'служебное значение' => ['__none__'];
        yield 'слишком длинное имя' => [str_repeat('я', MediaAsset::FOLDER_MAX_LENGTH + 1)];
    }

    #[DataProvider('invalidMetadata')]
    public function testMetadataValidationRejectsTooLongValues(string $field, int $length): void
    {
        $client = $this->adminClient();
        $asset = $this->seedAsset('meta.jpg');

        $this->api($client, 'PATCH', '/admin/api/media/assets/'.$asset->id(), [$field => str_repeat('a', $length)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->json($client)['code'] ?? null);
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function invalidMetadata(): iterable
    {
        yield 'alt' => ['alt', MediaAsset::METADATA_MAX_LENGTH + 1];
        yield 'title' => ['title', MediaAsset::METADATA_MAX_LENGTH + 1];
        yield 'description' => ['description', MediaAsset::DESCRIPTION_MAX_LENGTH + 1];
        yield 'folder' => ['folder', MediaAsset::FOLDER_MAX_LENGTH + 1];
    }

    public function testMetadataAtExactLimitIsAccepted(): void
    {
        $client = $this->adminClient();
        $asset = $this->seedAsset('limit.jpg');

        $this->api($client, 'PATCH', '/admin/api/media/assets/'.$asset->id(), [
            'alt' => str_repeat('я', MediaAsset::METADATA_MAX_LENGTH),
            'description' => str_repeat('d', MediaAsset::DESCRIPTION_MAX_LENGTH),
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testPatchWithMalformedJsonReturnsBadRequestWithoutParserDetails(): void
    {
        $client = $this->adminClient();
        $asset = $this->seedAsset('json.jpg');

        $token = $this->csrf($client);
        $client->request('PATCH', '/admin/api/media/assets/'.$asset->id(), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $token,
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ], content: '{broken');

        self::assertResponseStatusCodeSame(400);
        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Syntax error', $content);
        self::assertStringNotContainsString('JSON', $content);
    }

    public function testOperationsOnUnknownAssetReturnNotFound(): void
    {
        $client = $this->adminClient();
        $unknown = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        $this->api($client, 'PATCH', '/admin/api/media/assets/'.$unknown, ['alt' => 'x']);
        self::assertResponseStatusCodeSame(404);

        $this->api($client, 'DELETE', '/admin/api/media/assets/'.$unknown);
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/admin/api/media/assets/'.$unknown.'/usages');
        self::assertResponseStatusCodeSame(404);
    }

    public function testListPaginationBoundaries(): void
    {
        $client = $this->adminClient();
        for ($index = 1; $index <= 3; ++$index) {
            $this->seedAsset(\sprintf('p%d.jpg', $index));
        }

        $client->request('GET', '/admin/api/media/assets?perPage=100&page=1');
        self::assertResponseIsSuccessful();
        self::assertSame(['page' => 1, 'perPage' => 100, 'total' => 3, 'totalPages' => 1], $this->json($client)['pagination'] ?? null);

        $client->request('GET', '/admin/api/media/assets?perPage=2&page=99');
        self::assertResponseIsSuccessful();
        $beyond = $this->json($client);
        self::assertSame([], $beyond['assets'] ?? null);
        self::assertSame(3, $this->pagination($beyond)['total'] ?? null);

        foreach (['perPage=0', 'perPage=101', 'page=0', 'page=-1', 'page=abc', 'perPage=1.5'] as $query) {
            $client->request('GET', '/admin/api/media/assets?'.$query);
            self::assertResponseStatusCodeSame(422, $query);
            self::assertSame('VALIDATION', $this->json($client)['code'] ?? null, $query);
        }
    }

    public function testSearchTreatsWildcardsAndOverlongQueriesSafely(): void
    {
        $client = $this->adminClient();
        $this->seedAsset('real-photo.jpg');

        $client->request('GET', '/admin/api/media/assets?q='.rawurlencode('%'));
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json($client)['assets'] ?? null, 'Спецсимвол LIKE не должен совпадать со всеми записями.');

        $client->request('GET', '/admin/api/media/assets?q='.rawurlencode("'; DROP TABLE media_asset;--"));
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json($client)['assets'] ?? null);

        $client->request('GET', '/admin/api/media/assets?q='.str_repeat('a', MediaAsset::METADATA_MAX_LENGTH));
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function upload(KernelBrowser $client, string $path, string $clientName, string $clientMime, array $parameters = []): void
    {
        $this->multipart($client, ['file' => new UploadedFile($path, $clientName, $clientMime, null, true)], $parameters);
    }

    /**
     * @param array<string, UploadedFile> $files
     * @param array<string, string>       $parameters
     */
    private function multipart(KernelBrowser $client, array $files, array $parameters): void
    {
        $client->request('POST', '/admin/api/media/assets', $parameters, $files, [
            'HTTP_X_CSRF_TOKEN' => $this->csrf($client),
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ]);
    }

    private function csrf(KernelBrowser $client): string
    {
        $client->request('GET', '/admin/dashboard');
        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', (string) $client->getResponse()->getContent(), $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        return $matches[1];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function pagination(array $payload): array
    {
        $pagination = $payload['pagination'] ?? null;
        self::assertIsArray($pagination);

        $normalized = [];
        foreach ($pagination as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    private function seedAsset(string $name): MediaAsset
    {
        $asset = new MediaAsset($name, strtolower($name), '/uploads/media/'.rawurlencode($name), 'image/jpeg', 100, null, null);
        $this->assets()->save($asset);

        return $asset;
    }

    private function assets(): MediaAssetRepositoryInterface
    {
        $assets = self::getContainer()->get(MediaAssetRepositoryInterface::class);
        if (!$assets instanceof MediaAssetRepositoryInterface) {
            throw new LogicException('Media asset repository is not available.');
        }

        return $assets;
    }

    /**
     * @return list<string>
     */
    private function storedFiles(): array
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);

        $files = glob($projectDir.'/public_html/uploads/media/*') ?: [];
        sort($files);

        return $files;
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(self::ONE_PIXEL_PNG, true);
        if (!\is_string($bytes)) {
            throw new LogicException('Fixture PNG cannot be decoded.');
        }

        return $bytes;
    }

    private function pngHeaderOnly(int $width, int $height): string
    {
        $ihdrData = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $chunk = static fn (string $type, string $data): string => pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', $ihdrData).$chunk('IEND', '');
    }

    private function temporaryFile(string $content): string
    {
        $path = sys_get_temp_dir().'/media-edge-'.bin2hex(random_bytes(6)).'.bin';
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
