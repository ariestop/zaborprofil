<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Command\CreatePageBlockCommand;
use App\Module\Content\Application\Command\DeletePageBlockCommand;
use App\Module\Content\Application\Command\ReorderPageBlocksCommand;
use App\Module\Content\Application\Command\UpdatePageBlockCommand;
use App\Module\Content\Application\Handler\CreatePageBlockHandler;
use App\Module\Content\Application\Handler\DeletePageBlockHandler;
use App\Module\Content\Application\Handler\ReorderPageBlocksHandler;
use App\Module\Content\Application\Handler\UpdatePageBlockHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/content')]
final readonly class PageBlockApiController
{
    public function __construct(
        private JsonRequest $jsonRequest,
        private ContentApiResponder $responder,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('/pages/{pageId}/blocks', name: 'admin_api_content_page_block_create', methods: ['POST'])]
    public function create(string $pageId, Request $request, CreatePageBlockHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $block = $handler(new CreatePageBlockCommand(
                $pageId,
                $this->jsonRequest->string($payload, 'type'),
                $this->jsonRequest->string($payload, 'name'),
                $this->jsonRequest->int($payload, 'position', 0),
                $this->jsonRequest->object($payload, 'content'),
                $this->jsonRequest->object($payload, 'settings'),
                $this->jsonRequest->bool($payload, 'isEnabled', true),
            ));

            return new JsonResponse($block->toArray(), 201);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/blocks/{id}', name: 'admin_api_content_page_block_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdatePageBlockHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $block = $handler(new UpdatePageBlockCommand(
                $id,
                $this->jsonRequest->string($payload, 'type'),
                $this->jsonRequest->string($payload, 'name'),
                $this->jsonRequest->object($payload, 'content'),
                $this->jsonRequest->object($payload, 'settings'),
                $this->jsonRequest->bool($payload, 'isEnabled', true),
            ));

            return new JsonResponse($block->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/pages/{pageId}/blocks/reorder', name: 'admin_api_content_page_block_reorder', methods: ['POST'])]
    public function reorder(string $pageId, Request $request, ReorderPageBlocksHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $blocks = $handler(new ReorderPageBlocksCommand(
                $pageId,
                $this->jsonRequest->stringList($payload, 'blockIds'),
            ));

            return new JsonResponse([
                'blocks' => array_map(static fn ($block): array => $block->toArray(), $blocks),
            ]);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/blocks/{id}', name: 'admin_api_content_page_block_delete', methods: ['DELETE'])]
    public function delete(string $id, DeletePageBlockHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_DELETE)) {
            return $this->accessDenied();
        }

        try {
            $handler(new DeletePageBlockCommand($id));

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
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
