<?php

declare(strict_types=1);

namespace App\Module\Portfolio\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Portfolio\Application\Command\PortfolioCategoryInput;
use App\Module\Portfolio\Application\Command\PortfolioMediaInput;
use App\Module\Portfolio\Application\Command\PortfolioProjectInput;
use App\Module\Portfolio\Application\Service\PortfolioAdminService;
use App\Module\Portfolio\Domain\Entity\PortfolioCategory;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use App\Module\Portfolio\Domain\Repository\PortfolioCategoryRepositoryInterface;
use App\Module\Portfolio\Domain\Repository\PortfolioProjectRepositoryInterface;
use App\Shared\UI\Http\AdminApiErrorResponder;
use App\Shared\UI\Http\AdminApiResponses;
use App\Shared\UI\Http\JsonRequest;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

/**
 * Портфолио в админке: категории и работы (поля, медиа, порядок, публикация).
 * Работа сохраняется целиком: поля, характеристики и упорядоченный список медиа одним запросом.
 */
#[Route('/admin/api/portfolio')]
final readonly class PortfolioApiController
{
    public function __construct(
        private PortfolioCategoryRepositoryInterface $categories,
        private PortfolioProjectRepositoryInterface $projects,
        private PortfolioAdminService $portfolio,
        private JsonRequest $jsonRequest,
        private AuthorizationCheckerInterface $authorizationChecker,
        private AdminApiErrorResponder $errors,
    ) {
    }

    /**
     * Всё портфолио одним ответом: категории по порядку и работы (без медиа — только обложка и счётчики).
     */
    #[Route('', name: 'admin_api_portfolio_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PORTFOLIO_VIEW)) {
            return AdminApiResponses::accessDenied();
        }

        return new JsonResponse([
            'categories' => array_map(static fn (PortfolioCategory $category): array => $category->toArray(), $this->categories->findAllOrdered()),
            'projects' => array_map(static function (PortfolioProject $project): array {
                $row = $project->toArray();
                unset($row['media'], $row['description']);

                return $row;
            }, $this->projects->findAllForAdmin()),
        ]);
    }

    #[Route('/categories', name: 'admin_api_portfolio_category_create', methods: ['POST'])]
    public function createCategory(Request $request): JsonResponse
    {
        return $this->managed(fn (): JsonResponse => new JsonResponse($this->portfolio->createCategory($this->categoryInput($request))->toArray(), 201));
    }

    #[Route('/categories/{id}', name: 'admin_api_portfolio_category_update', methods: ['PUT'])]
    public function updateCategory(string $id, Request $request): JsonResponse
    {
        return $this->managed(fn (): JsonResponse => new JsonResponse($this->portfolio->updateCategory($id, $this->categoryInput($request))->toArray()));
    }

    #[Route('/categories/{id}', name: 'admin_api_portfolio_category_delete', methods: ['DELETE'])]
    public function deleteCategory(string $id): JsonResponse
    {
        return $this->managed(function () use ($id): JsonResponse {
            $this->portfolio->deleteCategory($id);

            return new JsonResponse(null, 204);
        });
    }

    /**
     * Тело: `{"ids": ["…", …]}` — все категории в новом порядке.
     */
    #[Route('/categories-order', name: 'admin_api_portfolio_category_reorder', methods: ['PUT'])]
    public function reorderCategories(Request $request): JsonResponse
    {
        return $this->managed(fn (): JsonResponse => new JsonResponse([
            'categories' => array_map(static fn (PortfolioCategory $category): array => $category->toArray(), $this->portfolio->reorderCategories($this->jsonRequest->stringList($this->jsonRequest->payload($request), 'ids'))),
        ]));
    }

    #[Route('/projects/{id}', name: 'admin_api_portfolio_project_show', methods: ['GET'])]
    public function showProject(string $id): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PORTFOLIO_VIEW)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            return new JsonResponse($this->projects->get($id)->toArray());
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Portfolio API');
        }
    }

    #[Route('/projects', name: 'admin_api_portfolio_project_create', methods: ['POST'])]
    public function createProject(Request $request): JsonResponse
    {
        return $this->managed(fn (): JsonResponse => new JsonResponse($this->portfolio->createProject($this->projectInput($request))->toArray(), 201));
    }

    #[Route('/projects/{id}', name: 'admin_api_portfolio_project_update', methods: ['PUT'])]
    public function updateProject(string $id, Request $request): JsonResponse
    {
        return $this->managed(fn (): JsonResponse => new JsonResponse($this->portfolio->updateProject($id, $this->projectInput($request))->toArray()));
    }

    #[Route('/projects/{id}', name: 'admin_api_portfolio_project_delete', methods: ['DELETE'])]
    public function deleteProject(string $id): JsonResponse
    {
        return $this->managed(function () use ($id): JsonResponse {
            $this->portfolio->deleteProject($id);

            return new JsonResponse(null, 204);
        });
    }

    /**
     * Тело: `{"published": true}`.
     */
    #[Route('/projects/{id}/status', name: 'admin_api_portfolio_project_status', methods: ['PUT'])]
    public function setStatus(string $id, Request $request): JsonResponse
    {
        return $this->managed(fn (): JsonResponse => new JsonResponse($this->portfolio->setPublished($id, $this->jsonRequest->bool($this->jsonRequest->payload($request), 'published', false))->toArray()));
    }

    /**
     * Тело: `{"ids": ["…", …]}` — все работы категории в новом порядке.
     */
    #[Route('/categories/{id}/projects-order', name: 'admin_api_portfolio_project_reorder', methods: ['PUT'])]
    public function reorderProjects(string $id, Request $request): JsonResponse
    {
        return $this->managed(fn (): JsonResponse => new JsonResponse([
            'projects' => array_map(static fn (PortfolioProject $project): array => $project->toArray(), $this->portfolio->reorderProjects($id, $this->jsonRequest->stringList($this->jsonRequest->payload($request), 'ids'))),
        ]));
    }

    /**
     * @param callable(): JsonResponse $action
     */
    private function managed(callable $action): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::PORTFOLIO_MANAGE)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            return $action();
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Portfolio API');
        }
    }

    private function categoryInput(Request $request): PortfolioCategoryInput
    {
        $payload = $this->jsonRequest->payload($request);

        return new PortfolioCategoryInput(
            $this->jsonRequest->string($payload, 'title', ''),
            $this->jsonRequest->string($payload, 'slug', ''),
            $this->jsonRequest->nullableString($payload, 'shortTitle'),
            $this->jsonRequest->nullableString($payload, 'description'),
        );
    }

    private function projectInput(Request $request): PortfolioProjectInput
    {
        $payload = $this->jsonRequest->payload($request);
        $year = $payload['year'] ?? null;
        if ($year !== null && !\is_int($year)) {
            throw new InvalidArgumentException('Год — число, например 2025.');
        }

        return new PortfolioProjectInput(
            $this->jsonRequest->string($payload, 'categoryId', ''),
            $this->jsonRequest->string($payload, 'title', ''),
            $this->jsonRequest->string($payload, 'slug', ''),
            $this->jsonRequest->nullableString($payload, 'place'),
            $year,
            $this->jsonRequest->nullableString($payload, 'summary'),
            $this->jsonRequest->string($payload, 'description', ''),
            array_map(fn (array $row): array => [
                'label' => $this->jsonRequest->string($row, 'label', ''),
                'value' => $this->jsonRequest->string($row, 'value', ''),
            ], $this->jsonRequest->nullableObjectList($payload, 'specs') ?? []),
            array_map(fn (array $row): PortfolioMediaInput => new PortfolioMediaInput(
                $this->jsonRequest->string($row, 'kind', ''),
                $this->jsonRequest->string($row, 'src', ''),
                $this->jsonRequest->nullableString($row, 'poster'),
                $this->jsonRequest->nullableString($row, 'caption'),
                $this->jsonRequest->bool($row, 'isCover', false),
            ), $this->jsonRequest->nullableObjectList($payload, 'media') ?? []),
            $this->jsonRequest->nullableString($payload, 'seoTitle'),
            $this->jsonRequest->nullableString($payload, 'seoDescription'),
        );
    }
}
