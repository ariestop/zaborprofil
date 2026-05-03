<?php

declare(strict_types=1);

namespace App\Module\Media\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Media\Application\Service\MediaOptimizer;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Shared\Infrastructure\Upload\UploadValidator;
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
    ) {
    }

    #[Route('/assets', name: 'admin_api_media_assets_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::MEDIA_UPLOAD)) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            'assets' => array_map(static fn (MediaAsset $asset): array => $asset->toArray(), $this->assets->findLatest()),
        ]);
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
                return new JsonResponse(['error' => 'Field "file" must contain an uploaded file.'], 400);
            }

            $validated = $this->uploadValidator->validate($file);
            if (!is_dir($this->mediaUploadDir) && !mkdir($this->mediaUploadDir, 0775, true) && !is_dir($this->mediaUploadDir)) {
                return new JsonResponse(['error' => 'Media upload directory cannot be created.'], 500);
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
            );
            $this->assets->save($asset);

            return new JsonResponse($asset->toArray(), 201);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    #[Route('/assets/{id}', name: 'admin_api_media_assets_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::MEDIA_DELETE)) {
            return $this->accessDenied();
        }

        try {
            $asset = $this->assets->get($id);
            $this->removeAssetFiles($asset);
            $this->assets->remove($asset);

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
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
