<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Command\ArchivePageCommand;
use App\Module\Content\Application\Command\BulkUpdatePagesCommand;
use App\Module\Content\Application\Command\CancelPageScheduleCommand;
use App\Module\Content\Application\Command\ChangePageStatusCommand;
use App\Module\Content\Application\Command\CreatePageCommand;
use App\Module\Content\Application\Command\DuplicatePageCommand;
use App\Module\Content\Application\Command\PublishPageCommand;
use App\Module\Content\Application\Command\SchedulePageCommand;
use App\Module\Content\Application\Command\UpdatePageCommand;
use App\Module\Content\Application\Command\UpdatePageSeoMetadataCommand;
use App\Module\Content\Application\DTO\PageBlockOutput;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Handler\ArchivePageHandler;
use App\Module\Content\Application\Handler\BulkUpdatePagesHandler;
use App\Module\Content\Application\Handler\CancelPageScheduleHandler;
use App\Module\Content\Application\Handler\ChangePageStatusHandler;
use App\Module\Content\Application\Handler\CreatePageHandler;
use App\Module\Content\Application\Handler\DuplicatePageHandler;
use App\Module\Content\Application\Handler\PublishPageHandler;
use App\Module\Content\Application\Handler\SchedulePageHandler;
use App\Module\Content\Application\Handler\UpdatePageHandler;
use App\Module\Content\Application\Handler\UpdatePageSeoMetadataHandler;
use App\Module\Content\Application\Service\AdminDisplayNames;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PagePreviewToken;
use App\Module\Content\Application\Service\PageRevisionComparison;
use App\Module\Content\Application\Service\PageWorkflowState;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageSearchCriteria;
use App\Module\Content\Domain\ValueObject\PageVisibility;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'admin_api_content_page_index', methods: ['GET'])]
    public function index(Request $request, PageRepositoryInterface $pages, PageRevisionComparison $comparison, AdminDisplayNames $adminNames): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return $this->accessDenied();
        }

        $names = $adminNames->all();

        if ($request->query->has('page') || $request->query->has('perPage')) {
            return $this->paginatedIndex($request, $pages, $names);
        }

        $all = $pages->findAllForAdmin();
        $changed = $this->unpublishedChanges($all, $comparison);

        return new JsonResponse([
            'pages' => array_map(
                fn (Page $page): array => [
                    ...$this->listItem($page, $names),
                    'hasUnpublishedChanges' => $changed[(string) $page->id()] ?? false,
                ],
                $all,
            ),
        ]);
    }

    /**
     * Строка списка страниц: данные страницы и подпись автора последней правки.
     *
     * @param array<string, string> $names
     *
     * @return array<string, mixed>
     */
    private function listItem(Page $page, array $names): array
    {
        $editor = $page->updatedBy();

        return [
            ...PageOutput::fromPage($page)->toArray(),
            'updatedByName' => $editor === null ? null : ($names[$editor] ?? null),
        ];
    }

    /**
     * Маркер «есть неопубликованные правки» для списка страниц: только у опубликованных, данные для сравнения
     * загружаются пачкой (без N+1); сбой сравнения одной страницы не ломает весь список.
     *
     * @param list<Page> $pages
     *
     * @return array<string, bool>
     */
    private function unpublishedChanges(array $pages, PageRevisionComparison $comparison): array
    {
        $published = array_values(array_filter($pages, static fn (Page $page): bool => $page->status() === PageStatus::Published));

        return $comparison->unpublishedChangesFor($published, function (Page $page, Throwable $exception): void {
            $this->logger->error('Page revision comparison failed while building the page list.', [
                'page_id' => (string) $page->id(),
                'exception' => $exception,
            ]);
        });
    }

    /**
     * @param array<string, string> $names
     */
    private function paginatedIndex(Request $request, PageRepositoryInterface $pages, array $names): JsonResponse
    {
        $criteria = new PageSearchCriteria(
            $request->query->getString('q') ?: null,
            PageStatus::tryFrom($request->query->getString('status')),
            $request->query->getInt('page', 1),
            $request->query->getInt('perPage', 25),
        );
        $result = $pages->searchForAdmin($criteria);
        $perPage = $criteria->normalizedPerPage();

        return new JsonResponse([
            'pages' => array_map(fn (Page $page): array => $this->listItem($page, $names), $result->items),
            'meta' => [
                'total' => $result->total,
                'page' => $criteria->normalizedPage(),
                'perPage' => $perPage,
                'pages' => max(1, (int) ceil($result->total / $perPage)),
            ],
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
                PageVisibility::from($this->jsonRequest->string($payload, 'visibility', PageVisibility::Public->value)),
                $this->jsonRequest->nullableString($payload, 'starterTemplate'),
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
                PageVisibility::from($this->jsonRequest->string($payload, 'visibility', PageVisibility::Public->value)),
            ));

            return new JsonResponse($page->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/bulk', name: 'admin_api_content_page_bulk', methods: ['POST'])]
    public function bulk(Request $request, BulkUpdatePagesHandler $handler): JsonResponse
    {
        try {
            $payload = $this->jsonRequest->payload($request);
            $action = $this->jsonRequest->string($payload, 'action');
            $status = $this->jsonRequest->nullableString($payload, 'status');
            $permission = match ($action) {
                BulkUpdatePagesCommand::ACTION_STATUS => $this->permissionForStatus($status ?? ''),
                BulkUpdatePagesCommand::ACTION_INDEXABLE => AdminPermission::SEO_EDIT,
                default => AdminPermission::PAGES_EDIT,
            };

            if (!$this->authorizationChecker->isGranted($permission)) {
                return $this->accessDenied();
            }

            $indexable = $payload['indexable'] ?? null;
            if ($indexable !== null && !\is_bool($indexable)) {
                throw new \InvalidArgumentException('Field "indexable" must be a boolean or null.');
            }

            $results = $handler(new BulkUpdatePagesCommand(
                $this->jsonRequest->stringList($payload, 'ids'),
                $action,
                $status,
                $indexable,
                $this->jsonRequest->nullableString($payload, 'comment'),
            ));
            $succeeded = \count(array_filter($results, static fn (array $result): bool => $result['ok']));

            return new JsonResponse([
                'results' => $results,
                'succeeded' => $succeeded,
                'failed' => \count($results) - $succeeded,
            ]);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/duplicate', name: 'admin_api_content_page_duplicate', methods: ['POST'])]
    public function duplicate(string $id, Request $request, DuplicatePageHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_CREATE)) {
            return $this->accessDenied();
        }

        try {
            $payload = $request->getContent() === '' ? [] : $this->jsonRequest->payload($request);

            return new JsonResponse($handler(new DuplicatePageCommand(
                $id,
                $this->jsonRequest->nullableString($payload, 'title'),
                $this->jsonRequest->nullableString($payload, 'slug'),
                $this->jsonRequest->nullableString($payload, 'path'),
            ))->toArray(), 201);
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
                $this->jsonRequest->nullableString($payload, 'metaTitle'),
                \array_key_exists('metaTitle', $payload),
            ));

            return new JsonResponse($page->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/publish', name: 'admin_api_content_page_publish', methods: ['POST'])]
    public function publish(string $id, Request $request, PublishPageHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_PUBLISH)) {
            return $this->accessDenied();
        }

        try {
            $payload = $request->getContent() === '' ? [] : $this->jsonRequest->payload($request);

            return new JsonResponse($handler(new PublishPageCommand($id, $this->jsonRequest->nullableString($payload, 'comment')))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/status', name: 'admin_api_content_page_status', methods: ['PATCH'])]
    public function changeStatus(string $id, Request $request, ChangePageStatusHandler $handler): JsonResponse
    {
        try {
            $payload = $this->jsonRequest->payload($request);
            $status = $this->jsonRequest->string($payload, 'status');
            if (!$this->authorizationChecker->isGranted($this->permissionForStatus($status))) {
                return $this->accessDenied();
            }

            return new JsonResponse($handler(new ChangePageStatusCommand(
                $id,
                $status,
                $this->jsonRequest->nullableString($payload, 'comment'),
                $this->jsonRequest->nullableString($payload, 'publishAt'),
                $this->jsonRequest->nullableString($payload, 'unpublishAt'),
            ))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/schedule', name: 'admin_api_content_page_schedule', methods: ['POST'])]
    public function schedule(string $id, Request $request, SchedulePageHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_SCHEDULE)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);

            return new JsonResponse($handler(new SchedulePageCommand(
                $id,
                $this->jsonRequest->nullableString($payload, 'publishAt'),
                $this->jsonRequest->nullableString($payload, 'unpublishAt'),
                $this->jsonRequest->nullableString($payload, 'comment'),
            ))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/schedule', name: 'admin_api_content_page_schedule_cancel', methods: ['DELETE'])]
    public function cancelSchedule(string $id, Request $request, CancelPageScheduleHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_SCHEDULE)) {
            return $this->accessDenied();
        }

        try {
            $payload = $request->getContent() === '' ? [] : $this->jsonRequest->payload($request);

            return new JsonResponse($handler(new CancelPageScheduleCommand($id, $this->jsonRequest->nullableString($payload, 'comment')))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/workflow', name: 'admin_api_content_page_workflow', methods: ['GET'])]
    public function workflow(string $id, PageWorkflowState $state): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse($state->describe($id));
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

    private function permissionForStatus(string $status): string
    {
        return match ($status) {
            'draft', 'review' => AdminPermission::PAGES_SUBMIT_REVIEW,
            'published' => AdminPermission::PAGES_PUBLISH,
            'approved' => AdminPermission::PAGES_APPROVE,
            'unpublished' => AdminPermission::PAGES_UNPUBLISH,
            'scheduled' => AdminPermission::PAGES_SCHEDULE,
            'archived' => AdminPermission::PAGES_ARCHIVE,
            'deleted' => AdminPermission::PAGES_DELETE,
            default => AdminPermission::PAGES_EDIT,
        };
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }
}
