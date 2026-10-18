<?php

declare(strict_types=1);

namespace App\Module\Menu\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Menu\Application\Command\MenuItemInput;
use App\Module\Menu\Application\Command\MenuOrderEntry;
use App\Module\Menu\Application\Service\MenuTreeService;
use App\Module\Menu\Domain\Entity\MenuItem;
use App\Module\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Module\Menu\Domain\ValueObject\MenuPosition;
use App\Shared\UI\Http\AdminApiErrorResponder;
use App\Shared\UI\Http\AdminApiResponses;
use App\Shared\UI\Http\JsonRequest;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

/**
 * Меню сайта в админке: список, создание, правка (в том числе перенос в другую позицию или раздел),
 * удаление вместе с подпунктами и новый порядок позиции одним запросом.
 */
#[Route('/admin/api/menu')]
final readonly class MenuApiController
{
    public function __construct(
        private MenuItemRepositoryInterface $items,
        private MenuTreeService $tree,
        private JsonRequest $jsonRequest,
        private AuthorizationCheckerInterface $authorizationChecker,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('/items', name: 'admin_api_menu_item_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        return new JsonResponse([
            'items' => array_map(static fn (MenuItem $item): array => $item->toArray(), $this->items->findAllForAdmin()),
            'positions' => MenuPosition::options(),
        ]);
    }

    #[Route('/items', name: 'admin_api_menu_item_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            return new JsonResponse($this->tree->create($this->input($request))->toArray(), 201);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Menu API');
        }
    }

    #[Route('/items/{id}', name: 'admin_api_menu_item_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            return new JsonResponse($this->tree->update($id, $this->input($request))->toArray());
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Menu API');
        }
    }

    #[Route('/items/{id}', name: 'admin_api_menu_item_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $this->tree->delete($id);

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Menu API');
        }
    }

    /**
     * Тело: `{"items": [{"id": "…", "parentId": null}, …]}` — все пункты позиции в новом порядке.
     */
    #[Route('/positions/{position}/order', name: 'admin_api_menu_reorder', methods: ['PUT'])]
    public function reorder(string $position, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $rows = $this->jsonRequest->nullableObjectList($this->jsonRequest->payload($request), 'items')
                ?? throw new InvalidArgumentException('Field "items" must be an array of objects.');
            $entries = array_map(
                fn (array $row): MenuOrderEntry => new MenuOrderEntry(
                    $this->jsonRequest->string($row, 'id'),
                    $this->jsonRequest->nullableString($row, 'parentId'),
                ),
                $rows,
            );

            return new JsonResponse([
                'items' => array_map(static fn (MenuItem $item): array => $item->toArray(), $this->tree->reorder($position, $entries)),
            ]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Menu API');
        }
    }

    private function input(Request $request): MenuItemInput
    {
        $payload = $this->jsonRequest->payload($request);
        $sortOrder = $payload['sortOrder'] ?? null;

        return new MenuItemInput(
            $this->jsonRequest->string($payload, 'position'),
            $this->jsonRequest->string($payload, 'label'),
            $this->jsonRequest->string($payload, 'url'),
            $this->jsonRequest->nullableString($payload, 'hint'),
            $this->jsonRequest->bool($payload, 'isActive', true),
            $this->jsonRequest->nullableString($payload, 'parentId'),
            $sortOrder === null ? null : $this->jsonRequest->int($payload, 'sortOrder'),
        );
    }
}
