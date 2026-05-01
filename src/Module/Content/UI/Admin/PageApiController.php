<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Content\Application\Command\ArchivePageCommand;
use App\Module\Content\Application\Command\CreatePageCommand;
use App\Module\Content\Application\Command\PublishPageCommand;
use App\Module\Content\Application\Command\UpdatePageCommand;
use App\Module\Content\Application\Handler\ArchivePageHandler;
use App\Module\Content\Application\Handler\CreatePageHandler;
use App\Module\Content\Application\Handler\PublishPageHandler;
use App\Module\Content\Application\Handler\UpdatePageHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/admin/api/content/pages')]
final readonly class PageApiController
{
    public function __construct(
        private JsonRequest $jsonRequest,
        private ContentApiResponder $responder,
    ) {
    }

    #[Route('', name: 'admin_api_content_page_create', methods: ['POST'])]
    public function create(Request $request, CreatePageHandler $handler): JsonResponse
    {
        try {
            $payload = $this->jsonRequest->payload($request);
            $page = $handler(new CreatePageCommand(
                $this->jsonRequest->string($payload, 'type'),
                $this->jsonRequest->string($payload, 'title'),
                $this->jsonRequest->string($payload, 'slug'),
                $this->jsonRequest->string($payload, 'path'),
                $this->jsonRequest->string($payload, 'h1'),
                $this->jsonRequest->string($payload, 'template', 'default'),
                $this->jsonRequest->int($payload, 'sortOrder', 0),
                $this->jsonRequest->bool($payload, 'isIndexable', true),
                $this->jsonRequest->nullableString($payload, 'parentId'),
            ));

            return new JsonResponse($page->toArray(), 201);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}', name: 'admin_api_content_page_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdatePageHandler $handler): JsonResponse
    {
        try {
            $payload = $this->jsonRequest->payload($request);
            $page = $handler(new UpdatePageCommand(
                $id,
                $this->jsonRequest->string($payload, 'type'),
                $this->jsonRequest->string($payload, 'title'),
                $this->jsonRequest->string($payload, 'slug'),
                $this->jsonRequest->string($payload, 'path'),
                $this->jsonRequest->string($payload, 'h1'),
                $this->jsonRequest->string($payload, 'template', 'default'),
                $this->jsonRequest->int($payload, 'sortOrder', 0),
                $this->jsonRequest->bool($payload, 'isIndexable', true),
                $this->jsonRequest->nullableString($payload, 'parentId'),
            ));

            return new JsonResponse($page->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/publish', name: 'admin_api_content_page_publish', methods: ['POST'])]
    public function publish(string $id, PublishPageHandler $handler): JsonResponse
    {
        try {
            return new JsonResponse($handler(new PublishPageCommand($id))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/archive', name: 'admin_api_content_page_archive', methods: ['POST'])]
    public function archive(string $id, ArchivePageHandler $handler): JsonResponse
    {
        try {
            return new JsonResponse($handler(new ArchivePageCommand($id))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }
}
