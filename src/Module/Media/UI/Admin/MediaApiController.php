<?php

declare(strict_types=1);

namespace App\Module\Media\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Media\Application\Service\MediaOptimizer;
use App\Module\Media\Application\Usage\MediaUsageFinder;
use App\Module\Media\Application\Usage\MediaUsageIndex;
use App\Module\Media\Application\Usage\MediaUsageReference;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Shared\Infrastructure\Upload\UploadValidator;
use App\Shared\UI\Http\AdminApiErrorResponder;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/media')]
final readonly class MediaApiController
{
    public function __construct(
        private MediaAssetRepositoryInterface $assets,
        private UploadValidator $uploadValidator,
        private AuthorizationCheckerInterface $authorizationChecker,
        private string $mediaUploadDir,
        private MediaOptimizer $mediaOptimizer,
        private AdminApiErrorResponder $errors,
        private MediaUsageFinder $usageFinder,
    ) {
    }

    #[Route('/assets', name: 'admin_api_media_assets_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::MEDIA_UPLOAD)) {
            return $this->accessDenied();
        }

        try {
            $usage = $this->usageFinder->index();
            $criteria = MediaListRequest::criteriaFrom(
                $request,
                fn (): array => $usage->usedAssetIds($this->assets->publicPathMap()),
            );
            $page = $this->assets->search($criteria);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Media API');
        }

        return new JsonResponse([
            'assets' => array_map(static fn (MediaAsset $asset): array => $asset->toArray() + ['usageCount' => $usage->count($asset)], $page->items),
            'pagination' => [
                'page' => $page->page,
                'perPage' => $page->perPage,
                'total' => $page->total,
                'totalPages' => $page->totalPages(),
            ],
        ]);
    }

    #[Route('/folders', name: 'admin_api_media_folders_index', methods: ['GET'])]
    public function folders(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::MEDIA_UPLOAD)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse(['folders' => $this->assets->folders()]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Media API');
        }
    }

    #[Route('/assets/{id}/usages', name: 'admin_api_media_assets_usages', methods: ['GET'])]
    public function usages(string $id): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::MEDIA_UPLOAD)) {
            return $this->accessDenied();
        }

        try {
            $asset = $this->assets->get($id);

            return new JsonResponse($this->usagePayload($this->usageFinder->index(), $asset));
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Media API');
        }
    }

    #[Route('/assets', name: 'admin_api_media_assets_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::MEDIA_UPLOAD)) {
            return $this->accessDenied();
        }

        try {
            $file = $request->files->get('file');
            if (!$file instanceof UploadedFile) {
                return $this->errors->badRequest('Field "file" must contain an uploaded file.');
            }

            if (\in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                return $this->errors->validation('Uploaded file exceeds the allowed size.', 'FILE_TOO_LARGE');
            }

            $validated = $this->uploadValidator->validate($file);
            $folder = MediaAsset::normalizeFolder($this->optionalFormString($request, 'folder'));
            $fileHash = hash_file('sha256', $file->getPathname());
            if ($fileHash === false) {
                throw new RuntimeException('Uploaded file hash cannot be calculated.');
            }

            $duplicate = $this->assets->findOneByFileHash($fileHash);
            if ($duplicate instanceof MediaAsset) {
                return new JsonResponse($duplicate->toArray() + [
                    'duplicate' => true,
                    'usageCount' => $this->usageFinder->index()->count($duplicate),
                ]);
            }

            if (!is_dir($this->mediaUploadDir) && !mkdir($this->mediaUploadDir, 0775, true) && !is_dir($this->mediaUploadDir)) {
                throw new RuntimeException('Media upload directory cannot be created.');
            }
            $storedFile = $file->move($this->mediaUploadDir, $validated->safeFilename);
            $publicPath = '/uploads/media/'.$validated->safeFilename;
            $optimized = $this->mediaOptimizer->optimize(
                $storedFile->getPathname(),
                $publicPath,
                $validated->mimeType,
                $validated->width,
                $validated->height,
            );

            $asset = new MediaAsset(
                $file->getClientOriginalName(),
                $validated->safeFilename,
                $publicPath,
                $validated->mimeType,
                $optimized->size,
                $optimized->width,
                $optimized->height,
                $optimized->variants,
                $fileHash,
            );
            if ($folder !== null) {
                $asset->updateMetadata(null, null, null, $folder);
            }
            $this->assets->save($asset);

            return new JsonResponse($asset->toArray() + ['duplicate' => false, 'usageCount' => 0], 201);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Media API');
        }
    }

    #[Route('/assets/{id}', name: 'admin_api_media_assets_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::MEDIA_UPLOAD)) {
            return $this->accessDenied();
        }

        try {
            $payload = $request->toArray();
            $asset = $this->assets->get($id);
            $asset->updateMetadata(
                $this->metadataValue($payload, 'alt', $asset->alt()),
                $this->metadataValue($payload, 'title', $asset->title()),
                $this->metadataValue($payload, 'description', $asset->description()),
                $this->metadataValue($payload, 'folder', $asset->folder()),
            );
            $this->assets->save($asset);

            return new JsonResponse($asset->toArray() + ['usageCount' => $this->usageFinder->index()->count($asset)]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Media API');
        }
    }

    #[Route('/assets/{id}', name: 'admin_api_media_assets_delete', methods: ['DELETE'])]
    public function delete(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::MEDIA_DELETE)) {
            return $this->accessDenied();
        }

        try {
            $asset = $this->assets->get($id);
            if (!$request->query->getBoolean('force')) {
                $payload = $this->usagePayload($this->usageFinder->index(), $asset);
                if ($payload['total'] > 0) {
                    return $this->errors->conflict(
                        'Media asset is used on the site. Confirm deletion to remove it anyway.',
                        'MEDIA_IN_USE',
                        $payload,
                    );
                }
            }

            $this->removeAssetFiles($asset);
            $this->assets->remove($asset);

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Media API');
        }
    }

    /**
     * @return array{total: int, usages: list<array<string, mixed>>}
     */
    private function usagePayload(MediaUsageIndex $index, MediaAsset $asset): array
    {
        $usages = array_map(
            static fn (MediaUsageReference $reference): array => $reference->toArray(),
            $index->forAsset($asset),
        );

        return ['total' => \count($usages), 'usages' => $usages];
    }

    private function optionalFormString(Request $request, string $key): ?string
    {
        $value = $request->request->get($key);

        return \is_string($value) ? $value : null;
    }

    /**
     * Ключ отсутствует в теле запроса — значение не меняется; `null` или пустая строка очищают поле.
     *
     * @param array<mixed> $payload
     */
    private function metadataValue(array $payload, string $key, ?string $current): ?string
    {
        if (!\array_key_exists($key, $payload)) {
            return $current;
        }

        return $this->optionalString($payload, $key);
    }

    /**
     * @param array<mixed> $payload
     */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a string.', $key));
        }

        return $value;
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }

    private function removeAssetFiles(MediaAsset $asset): void
    {
        $paths = [$asset->publicPath()];
        foreach ($asset->variants() as $variant) {
            $paths[] = $variant['publicPath'];
        }

        foreach ($paths as $publicPath) {
            $absolutePath = $this->absoluteMediaPath($publicPath);
            if ($absolutePath !== null && is_file($absolutePath)) {
                unlink($absolutePath);
            }
        }
    }

    private function absoluteMediaPath(string $publicPath): ?string
    {
        $prefix = '/uploads/media/';
        if (!str_starts_with($publicPath, $prefix)) {
            return null;
        }

        $relativePath = substr($publicPath, \strlen($prefix));
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        return rtrim($this->mediaUploadDir, '/').'/'.$relativePath;
    }
}
