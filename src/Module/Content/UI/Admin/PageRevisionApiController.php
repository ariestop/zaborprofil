<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Command\RollbackPageRevisionCommand;
use App\Module\Content\Application\DTO\PageRevisionOutput;
use App\Module\Content\Application\Handler\RollbackPageRevisionHandler;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/content/pages/{pageId}/revisions')]
final readonly class PageRevisionApiController
{
    public function __construct(
        private ContentApiResponder $responder,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_content_page_revisions_index', methods: ['GET'])]
    public function index(string $pageId, PageRevisionRepositoryInterface $revisions): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW_REVISIONS)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse([
                'revisions' => array_map(static fn ($revision): array => PageRevisionOutput::fromRevision($revision)->toArray(), $revisions->findByPage($pageId)),
            ]);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/{revisionId}/rollback', name: 'admin_api_content_page_revision_rollback', methods: ['POST'])]
    public function rollback(string $pageId, string $revisionId, RollbackPageRevisionHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_ROLLBACK_REVISION)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse($handler(new RollbackPageRevisionCommand($pageId, $revisionId))->toArray());
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
