<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Seo\Application\Audit\SeoAuditEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/seo/audit')]
final readonly class SeoAuditApiController
{
    public function __construct(
        private SeoAuditEngine $audit,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('/pages/{id}', name: 'admin_api_seo_audit_page', methods: ['GET'])]
    public function page(string $id, ContentId $contentId, PageRepositoryInterface $pages): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return new JsonResponse([
                'error' => 'Access denied.',
                'code' => 'ACCESS_DENIED',
            ], 403);
        }

        try {
            return new JsonResponse($this->audit->auditPage($pages->get($contentId->fromString($id)))->toArray());
        } catch (Throwable $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
                'code' => 'SEO_AUDIT_FAILED',
            ], 422);
        }
    }
}
