<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Command\PublishPageCommand;
use App\Module\Content\Application\Command\RollbackPageRevisionCommand;
use App\Module\Content\Application\DTO\BuilderBlockOutput;
use App\Module\Content\Application\DTO\PageRevisionOutput;
use App\Module\Content\Application\DTO\PageBuilderDocumentOutput;
use App\Module\Content\Application\Handler\PublishPageHandler;
use App\Module\Content\Application\Handler\RollbackPageRevisionHandler;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Application\Service\StructuredBlockDocumentService;
use App\Module\Content\Application\Service\PageBlockView;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use App\Module\Content\Domain\ValueObject\PageVisibility;
use App\Module\Content\UI\Web\TwigBlockRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/content/pages')]
final readonly class PageBuilderApiController
{
    public function __construct(
        private JsonRequest $jsonRequest,
        private ContentApiResponder $responder,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('/{id}/builder', name: 'admin_api_content_page_builder_show', methods: ['GET'])]
    public function show(
        string $id,
        ContentId $contentId,
        PageRepositoryInterface $pages,
        PageBlockRepositoryInterface $blocks,
    ): JsonResponse {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return $this->accessDenied();
        }

        try {
            $pageId = $contentId->fromString($id);
            $page = $pages->get($pageId);
            $items = array_map(static fn (PageBlock $block): BuilderBlockOutput => BuilderBlockOutput::fromBlock($block), $blocks->findByPage($pageId));

            return new JsonResponse(PageBuilderDocumentOutput::fromPage($page, $items)->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/builder', name: 'admin_api_content_page_builder_save', methods: ['PUT'])]
    public function save(
        string $id,
        Request $request,
        ContentId $contentId,
        PageRepositoryInterface $pages,
        PageBlockRepositoryInterface $blocks,
        StructuredBlockDocumentService $documentService,
        PublicPageCacheInvalidator $cacheInvalidator,
    ): JsonResponse {
        if (!$this->authorizationChecker->isGranted(AdminPermission::BLOCKS_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $rawBlocks = $payload['blocks'] ?? [];
            if (!\is_array($rawBlocks)) {
                throw new \InvalidArgumentException('Field "blocks" must be an array.');
            }

            $pageId = $contentId->fromString($id);
            $page = $pages->get($pageId);
            $existing = $blocks->findByPage($pageId);
            $existingById = [];
            foreach ($existing as $block) {
                $existingById[(string) $block->id()] = $block;
            }

            $preparedBlocks = [];
            $persist = [];
            $keptIds = [];
            foreach (array_values($rawBlocks) as $position => $rawBlock) {
                if (!\is_array($rawBlock)) {
                    throw new \InvalidArgumentException('Each block must be an object.');
                }

                /** @var array<string, mixed> $stringKeyed */
                $stringKeyed = [];
                foreach ($rawBlock as $key => $value) {
                    if (!\is_string($key)) {
                        throw new \InvalidArgumentException('Each block must be an object.');
                    }
                    $stringKeyed[$key] = $value;
                }

                $parsed = $documentService->parseBlockPayload($stringKeyed, $position);
                $keptIds[] = $parsed['id'];

                $existingBlock = $existingById[$parsed['id']] ?? null;
                if ($existingBlock instanceof PageBlock) {
                    $existingBlock->update(
                        $parsed['type'],
                        $this->nameFromType($parsed['type']->value),
                        $parsed['content'],
                        $parsed['settings'],
                        $parsed['enabled'],
                        $existingBlock->visibility(),
                    );
                    $existingBlock->moveTo($position);
                    $persist[] = $existingBlock;
                    $preparedBlocks[] = BuilderBlockOutput::fromBlock($existingBlock);
                    continue;
                }

                $newBlock = new PageBlock(
                    $page,
                    $parsed['type'],
                    $this->nameFromType($parsed['type']->value),
                    $position,
                    $parsed['content'],
                    $parsed['settings'],
                    $parsed['enabled'],
                    PageVisibility::Public,
                );
                $persist[] = $newBlock;
                $preparedBlocks[] = BuilderBlockOutput::fromBlock($newBlock);
            }

            foreach ($existing as $block) {
                if (!\in_array((string) $block->id(), $keptIds, true)) {
                    $blocks->remove($block);
                }
            }

            if ($persist !== []) {
                $blocks->saveAll($persist);
            }
            $cacheInvalidator->invalidate($page->path());

            return new JsonResponse(PageBuilderDocumentOutput::fromPage($page, $preparedBlocks)->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/builder/preview', name: 'admin_api_content_page_builder_preview', methods: ['POST'])]
    public function preview(
        string $id,
        Request $request,
        StructuredBlockDocumentService $documentService,
        TwigBlockRenderer $blockRenderer,
    ): JsonResponse {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $rawBlocks = $payload['blocks'] ?? [];
            if (!\is_array($rawBlocks)) {
                throw new \InvalidArgumentException('Field "blocks" must be an array.');
            }

            $html = '';
            foreach (array_values($rawBlocks) as $position => $rawBlock) {
                if (!\is_array($rawBlock)) {
                    throw new \InvalidArgumentException('Each block must be an object.');
                }

                /** @var array<string, mixed> $stringKeyed */
                $stringKeyed = [];
                foreach ($rawBlock as $key => $value) {
                    if (!\is_string($key)) {
                        throw new \InvalidArgumentException('Each block must be an object.');
                    }
                    $stringKeyed[$key] = $value;
                }

                $parsed = $documentService->parseBlockPayload($stringKeyed, $position);
                $html .= $blockRenderer->render(PageBlockView::fromSnapshot([
                    'id' => $parsed['id'],
                    'type' => $parsed['type']->value,
                    'name' => $this->nameFromType($parsed['type']->value),
                    'position' => $position,
                    'content' => $parsed['content'],
                    'settings' => $parsed['settings'],
                ]));
            }

            return new JsonResponse(['html' => $html]);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/builder/publish', name: 'admin_api_content_page_builder_publish', methods: ['POST'])]
    public function publish(
        string $id,
        PublishPageHandler $handler,
    ): JsonResponse {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_PUBLISH)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse($handler(new PublishPageCommand($id, null))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/builder/versions', name: 'admin_api_content_page_builder_versions', methods: ['GET'])]
    public function versions(
        string $id,
        PageRevisionRepositoryInterface $revisions,
    ): JsonResponse {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW_REVISIONS)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse([
                'revisions' => array_map(
                    static fn ($revision): array => PageRevisionOutput::fromRevision($revision)->toArray(),
                    $revisions->findByPage($id),
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/builder/rollback', name: 'admin_api_content_page_builder_rollback', methods: ['POST'])]
    public function rollback(
        string $id,
        Request $request,
        RollbackPageRevisionHandler $handler,
    ): JsonResponse {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_ROLLBACK_REVISION)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $revisionId = $this->jsonRequest->string($payload, 'revisionId');

            return new JsonResponse($handler(new RollbackPageRevisionCommand($id, $revisionId))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    private function nameFromType(string $type): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace(['.', '-'], ' ', $type)));
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }
}
