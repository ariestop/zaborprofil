<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Command\ArchivePageCommand;
use App\Module\Content\Application\Command\CreatePageCommand;
use App\Module\Content\Application\Command\PublishPageCommand;
use App\Module\Content\Application\Command\UpdatePageCommand;
use App\Module\Content\Application\Command\UpdatePageSeoMetadataCommand;
use App\Module\Content\Application\DTO\PageBlockOutput;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Handler\ArchivePageHandler;
use App\Module\Content\Application\Handler\CreatePageHandler;
use App\Module\Content\Application\Handler\PublishPageHandler;
use App\Module\Content\Application\Handler\UpdatePageHandler;
use App\Module\Content\Application\Handler\UpdatePageSeoMetadataHandler;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PagePreviewToken;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/content/pages')]
final readonly class PageApiController
{
    public function __construct(
        private JsonRequest $jsonRequest,
        private ContentApiResponder $responder,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_content_page_index', methods: ['GET'])]
    public function index(PageRepositoryInterface $pages): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            'pages' => array_map(static fn ($page): array => PageOutput::fromPage($page)->toArray(), $pages->findAllForAdmin()),
        ]);
    }

    #[Route('/{id}', name: 'admin_api_content_page_show', methods: ['GET'])]
    public function show(string $id, ContentId $contentId, PageRepositoryInterface $pages, PageBlockRepositoryInterface $blocks): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return $this->accessDenied();
        }

        try {
            $pageId = $contentId->fromString($id);
            $page = PageOutput::fromPage($pages->get($pageId))->toArray();
            $page['blocks'] = array_map(
                static fn ($block): array => PageBlockOutput::fromBlock($block)->toArray(),
                $blocks->findByPage($pageId),
            );

            return new JsonResponse($page);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('', name: 'admin_api_content_page_create', methods: ['POST'])]
    public function create(Request $request, CreatePageHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_CREATE)) {
            return $this->accessDenied();
        }

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
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_EDIT)) {
            return $this->accessDenied();
        }

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

    #[Route('/{id}/seo', name: 'admin_api_content_page_seo_update', methods: ['PUT'])]
    public function updateSeo(string $id, Request $request, UpdatePageSeoMetadataHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $page = $handler(new UpdatePageSeoMetadataCommand(
                $id,
                $this->jsonRequest->nullableString($payload, 'metaDescription'),
                $this->jsonRequest->nullableString($payload, 'canonicalUrl'),
                $this->jsonRequest->nullableString($payload, 'ogTitle'),
                $this->jsonRequest->nullableString($payload, 'ogDescription'),
                $this->jsonRequest->nullableString($payload, 'ogImage'),
                $this->jsonRequest->nullableString($payload, 'ogType'),
                $this->jsonRequest->nullableObjectList($payload, 'jsonLd'),
            ));

            return new JsonResponse($page->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/publish', name: 'admin_api_content_page_publish', methods: ['POST'])]
    public function publish(string $id, PublishPageHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_PUBLISH)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse($handler(new PublishPageCommand($id))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/preview-link', name: 'admin_api_content_page_preview_link', methods: ['GET'])]
    public function previewLink(
        string $id,
        ContentId $contentId,
        PageRepositoryInterface $pages,
        PagePreviewToken $previewToken,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return $this->accessDenied();
        }

        try {
            $page = $pages->get($contentId->fromString($id));
            $previewUrl = $urlGenerator->generate('content_page_preview', [
                'id' => (string) $page->id(),
                'token' => $previewToken->forPage((string) $page->id()),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse([
                'previewUrl' => $previewUrl,
                'robots' => 'noindex,nofollow',
            ]);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/archive', name: 'admin_api_content_page_archive', methods: ['POST'])]
    public function archive(string $id, ArchivePageHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_DELETE)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse($handler(new ArchivePageCommand($id))->toArray());
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
