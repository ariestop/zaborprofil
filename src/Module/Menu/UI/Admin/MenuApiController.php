<?php

declare(strict_types=1);

namespace App\Module\Menu\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\UI\Admin\JsonRequest;
use App\Module\Menu\Application\Service\MenuProvider;
use App\Module\Menu\Domain\Entity\MenuItem;
use App\Module\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Module\Menu\Domain\ValueObject\MenuPosition;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/menu/items')]
final readonly class MenuApiController
{
    public function __construct(
        private MenuItemRepositoryInterface $items,
        private JsonRequest $jsonRequest,
        private MenuProvider $menuProvider,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_menu_item_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            'items' => array_map(static fn (MenuItem $item): array => $item->toArray(), $this->items->findAllForAdmin()),
            'positions' => MenuPosition::options(),
        ]);
    }

    #[Route('', name: 'admin_api_menu_item_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $item = new MenuItem(
                $this->jsonRequest->string($payload, 'position'),
                $this->jsonRequest->string($payload, 'label'),
                $this->jsonRequest->string($payload, 'url'),
                $this->jsonRequest->int($payload, 'sortOrder', 0),
                $this->jsonRequest->bool($payload, 'isActive', true),
            );
            $this->items->save($item);
            $this->menuProvider->invalidate($item->position());

            return new JsonResponse($item->toArray(), 201);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'admin_api_menu_item_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $item = $this->items->get($id);
            $previousPosition = $item->position();
            $item->update(
                $this->jsonRequest->string($payload, 'position'),
                $this->jsonRequest->string($payload, 'label'),
                $this->jsonRequest->string($payload, 'url'),
                $this->jsonRequest->int($payload, 'sortOrder', 0),
                $this->jsonRequest->bool($payload, 'isActive', true),
            );
            $this->items->save($item);
            $this->menuProvider->invalidate($previousPosition);
            $this->menuProvider->invalidate($item->position());

            return new JsonResponse($item->toArray());
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'admin_api_menu_item_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $item = $this->items->get($id);
            $position = $item->position();
            $this->items->remove($item);
            $this->menuProvider->invalidate($position);

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
}
