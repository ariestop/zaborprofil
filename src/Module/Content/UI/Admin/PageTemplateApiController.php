<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\DTO\PageTemplateOutput;
use App\Module\Content\Application\Service\BlockSchemaRegistry;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageTemplateRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/content')]
final readonly class PageTemplateApiController
{
    public function __construct(
        private ContentApiResponder $responder,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('/templates', name: 'admin_api_content_templates_index', methods: ['GET'])]
    public function templates(Request $request, PageTemplateRepositoryInterface $templates): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return $this->accessDenied();
        }

        try {
            $type = $request->query->get('pageType');
            $pageType = \is_string($type) && $type !== '' ? PageType::from($type) : null;

            return new JsonResponse([
                'templates' => array_map(static fn ($template): array => PageTemplateOutput::fromTemplate($template)->toArray(), $templates->findActive($pageType)),
            ]);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/block-schemas', name: 'admin_api_content_block_schemas', methods: ['GET'])]
    public function blockSchemas(BlockSchemaRegistry $schemas): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            'blockSchemas' => array_map(static fn ($schema): array => $schema->toArray(), $schemas->all()),
        ]);
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }
}
