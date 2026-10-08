<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Command\SavePageTemplateCommand;
use App\Module\Content\Application\DTO\PageTemplateOutput;
use App\Module\Content\Application\Handler\DeactivatePageTemplateHandler;
use App\Module\Content\Application\Handler\SavePageTemplateHandler;
use App\Module\Content\Application\Service\BlockSchemaRegistry;
use App\Module\Content\Domain\Entity\PageTemplate;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageTemplateRepositoryInterface;
use App\Shared\UI\Http\AdminApiResponses;
use App\Shared\UI\Http\JsonRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/content')]
final readonly class PageTemplateApiController
{
    public function __construct(
        private JsonRequest $jsonRequest,
        private ContentApiResponder $responder,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('/templates', name: 'admin_api_content_templates_index', methods: ['GET'])]
    public function templates(Request $request, PageTemplateRepositoryInterface $templates): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $type = $request->query->get('pageType');
            $pageType = \is_string($type) && $type !== '' ? PageType::from($type) : null;

            $kindParam = $request->query->get('kind');
            $kind = match ($kindParam) {
                PageTemplate::KIND_SECTION => PageTemplate::KIND_SECTION,
                'all' => null,
                default => PageTemplate::KIND_PAGE,
            };

            return new JsonResponse([
                'templates' => array_map(static fn ($template): array => PageTemplateOutput::fromTemplate($template)->toArray(), $templates->findActive($pageType, $kind)),
            ]);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/templates', name: 'admin_api_content_templates_create', methods: ['POST'])]
    public function create(Request $request, SavePageTemplateHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_MANAGE_TEMPLATES)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $output = $handler(new SavePageTemplateCommand(
                $this->jsonRequest->string($payload, 'name'),
                $this->jsonRequest->nullableString($payload, 'description'),
                $this->jsonRequest->string($payload, 'kind', PageTemplate::KIND_PAGE),
                $this->jsonRequest->string($payload, 'pageType', PageType::TextPage->value),
                $this->jsonRequest->nullableObjectList($payload, 'blocks') ?? [],
            ));

            return new JsonResponse($output->toArray(), 201);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/templates/{code}', name: 'admin_api_content_templates_delete', methods: ['DELETE'])]
    public function delete(string $code, DeactivatePageTemplateHandler $handler): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_MANAGE_TEMPLATES)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $handler($code);

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return $this->responder->error($exception);
        }
    }

    #[Route('/block-schemas', name: 'admin_api_content_block_schemas', methods: ['GET'])]
    public function blockSchemas(BlockSchemaRegistry $schemas): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PAGES_VIEW)) {
            return AdminApiResponses::accessDenied();
        }

        return new JsonResponse([
            'blockSchemas' => array_map(static fn ($schema): array => $schema->toArray(), $schemas->all()),
        ]);
    }

}
