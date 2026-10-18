<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Command\SavePageBuilderDocumentCommand;
use App\Module\Content\Application\DTO\BuilderBlockOutput;
use App\Module\Content\Application\DTO\PageBuilderDocumentOutput;
use App\Module\Content\Application\Exception\PageEditConflictException;
use App\Module\Content\Application\Handler\SavePageBuilderDocumentHandler;
use App\Module\Content\Application\Service\BuilderDocumentVersion;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\CurrentAdminActor;
use App\Module\Content\Application\Service\PageEditLockService;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\UI\Http\AdminApiResponses;
use App\Shared\UI\Http\JsonRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/content/pages')]
final readonly class PageBuilderApiController
{
    public const string CODE_EDIT_CONFLICT = 'EDIT_CONFLICT';

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
        BuilderDocumentVersion $versions,
    ): JsonResponse {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $pageId = $contentId->fromString($id);
            $page = $pages->get($pageId);
            $items = array_map(BuilderBlockOutput::fromBlock(...), $blocks->findByPage($pageId));

            return new JsonResponse(PageBuilderDocumentOutput::fromPage($page, $items, $versions->fromBlocks($items))->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/builder', name: 'admin_api_content_page_builder_save', methods: ['PUT'])]
    public function save(string $id, Request $request, SavePageBuilderDocumentHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::BLOCKS_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $rawBlocks = $payload['blocks'] ?? [];
            if (!\is_array($rawBlocks)) {
                throw new \InvalidArgumentException('Field "blocks" must be an array.');
            }

            $baseVersion = $payload['baseVersion'] ?? null;
            if ($baseVersion !== null && !\is_string($baseVersion)) {
                throw new \InvalidArgumentException('Field "baseVersion" must be a string.');
            }

            return new JsonResponse($handler(new SavePageBuilderDocumentCommand($id, $rawBlocks, $baseVersion))->toArray());
        } catch (PageEditConflictException $conflict) {
            return $this->responder->conflict($conflict->getMessage(), self::CODE_EDIT_CONFLICT, [
                'version' => $conflict->currentVersion,
                'updatedAt' => $conflict->updatedAt->format(DATE_ATOM),
            ]);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/edit-lock', name: 'admin_api_content_page_edit_lock_acquire', methods: ['POST'])]
    public function acquireEditLock(
        string $id,
        Request $request,
        ContentId $contentId,
        PageRepositoryInterface $pages,
        PageEditLockService $locks,
        CurrentAdminActor $actor,
    ): JsonResponse {
        if (!$this->authorizationChecker->isGranted(AdminPermission::BLOCKS_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $sessionId = $this->editSessionId($payload);
            $takeOver = ($payload['takeOver'] ?? false) === true;

            $pageId = $contentId->fromString($id);
            $pages->get($pageId);

            return new JsonResponse($locks->acquire(
                $pageId,
                $actor->id() ?? '',
                $actor->label() ?? '',
                $sessionId,
                $takeOver,
            )->toArray());
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{id}/edit-lock', name: 'admin_api_content_page_edit_lock_release', methods: ['DELETE'])]
    public function releaseEditLock(
        string $id,
        Request $request,
        ContentId $contentId,
        PageEditLockService $locks,
    ): JsonResponse|Response {
        if (!$this->authorizationChecker->isGranted(AdminPermission::BLOCKS_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $locks->release($contentId->fromString($id), $this->editSessionId($this->jsonRequest->payload($request)));

            return new Response(status: 204);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function editSessionId(array $payload): string
    {
        $sessionId = $payload['sessionId'] ?? null;
        if (!\is_string($sessionId) || preg_match('/^[A-Za-z0-9-]{8,64}$/', $sessionId) !== 1) {
            throw new \InvalidArgumentException('Field "sessionId" must be a string of 8-64 letters, digits or dashes.');
        }

        return $sessionId;
    }

}
