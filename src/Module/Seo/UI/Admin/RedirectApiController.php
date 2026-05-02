<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/seo/redirects')]
final readonly class RedirectApiController
{
    public function __construct(
        private RedirectRepositoryInterface $redirects,
        private AuthorizationCheckerInterface $authorizationChecker,
    )
    {
    }

    #[Route('', name: 'admin_api_seo_redirects_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        return new JsonResponse(array_map(
            self::redirectToArray(...),
            $this->redirects->findAllOrdered(),
        ));
    }

    #[Route('', name: 'admin_api_seo_redirects_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->payload($request);
            $sourcePath = $this->string($payload, 'sourcePath');
            if ($this->redirects->findBySourcePath($sourcePath) !== null) {
                throw new InvalidArgumentException('Redirect source path must be unique.');
            }

            $redirect = new Redirect(
                $sourcePath,
                $this->string($payload, 'targetPath'),
                $this->int($payload, 'statusCode', 301),
                $this->bool($payload, 'isActive', true),
            );

            $this->redirects->save($redirect);

            return new JsonResponse(self::redirectToArray($redirect), 201);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/{sourcePath}', name: 'admin_api_seo_redirects_update', requirements: ['sourcePath' => '.+'], methods: ['PUT'])]
    public function update(string $sourcePath, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $redirect = $this->redirects->findBySourcePath('/'.$sourcePath);
            if ($redirect === null) {
                return new JsonResponse(['error' => 'Redirect not found.'], 404);
            }

            $payload = $this->payload($request);
            $redirect->update(
                $this->string($payload, 'targetPath'),
                $this->int($payload, 'statusCode', $redirect->statusCode()),
                $this->bool($payload, 'isActive', $redirect->isActive()),
            );
            $this->redirects->save($redirect);

            return new JsonResponse(self::redirectToArray($redirect));
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);
        if (!\is_array($decoded)) {
            throw new InvalidArgumentException('Request body must be a JSON object.');
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('Request body must be a JSON object.');
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function int(array $payload, string $key, int $default): int
    {
        $value = $payload[$key] ?? $default;
        if (!\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be an integer.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function bool(array $payload, string $key, bool $default): bool
    {
        $value = $payload[$key] ?? $default;
        if (!\is_bool($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a boolean.', $key));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function redirectToArray(Redirect $redirect): array
    {
        return [
            'id' => (string) $redirect->id(),
            'sourcePath' => $redirect->sourcePath(),
            'targetPath' => $redirect->targetPath(),
            'statusCode' => $redirect->statusCode(),
            'isActive' => $redirect->isActive(),
            'hitCount' => $redirect->hitCount(),
            'lastHitAt' => $redirect->lastHitAt()?->format(DATE_ATOM),
            'updatedAt' => $redirect->updatedAt()->format(DATE_ATOM),
        ];
    }
}
