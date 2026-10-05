<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Seo\Application\Redirect\RedirectAnalyzer;
use App\Module\Seo\Application\Redirect\RedirectChange;
use App\Module\Seo\Application\Redirect\RedirectCsvExporter;
use App\Module\Seo\Application\Redirect\RedirectImporter;
use App\Module\Seo\Application\Redirect\RedirectManager;
use App\Module\Seo\Application\Redirect\RedirectValidationException;
use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use App\Module\Seo\Domain\Repository\RedirectSearchCriteria;
use App\Shared\UI\Http\AdminApiErrorResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/seo/redirects')]
final readonly class RedirectApiController
{
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private RedirectRepositoryInterface $redirects,
        private RedirectManager $manager,
        private RedirectAnalyzer $analyzer,
        private RedirectImporter $importer,
        private RedirectCsvExporter $exporter,
        private AuthorizationCheckerInterface $authorizationChecker,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('', name: 'admin_api_seo_redirects_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        try {
            $page = max(1, $request->query->getInt('page', 1));
            $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('perPage', 25)));
            $sort = $request->query->getString('sort', RedirectSearchCriteria::SORT_SOURCE);
            $result = $this->redirects->search(new RedirectSearchCriteria(
                $request->query->getString('q') ?: null,
                match ($request->query->getString('status')) {
                    'active' => true,
                    'inactive' => false,
                    default => null,
                },
                \in_array($sort, RedirectSearchCriteria::SORTS, true) ? $sort : RedirectSearchCriteria::SORT_SOURCE,
                $request->query->getString('direction') === 'desc',
                $page,
                $perPage,
            ));
            $counts = $this->redirects->counts();

            return new JsonResponse([
                'items' => array_map(self::redirectToArray(...), $result->items),
                'total' => $result->total,
                'page' => $page,
                'perPage' => $perPage,
                'pages' => max(1, (int) ceil($result->total / $perPage)),
                'counts' => [
                    'total' => $counts['total'],
                    'active' => $counts['active'],
                    'inactive' => $counts['total'] - $counts['active'],
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Redirect API');
        }
    }

    #[Route('/analysis', name: 'admin_api_seo_redirects_analysis', methods: ['GET'])]
    public function analysis(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        try {
            return new JsonResponse($this->analyzer->analyze());
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Redirect API');
        }
    }

    #[Route('/export', name: 'admin_api_seo_redirects_export', methods: ['GET'])]
    public function export(): Response
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        try {
            $response = new Response($this->exporter->export($this->redirects->findAllOrdered()));
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="redirects.csv"');

            return $response;
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Redirect API');
        }
    }

    #[Route('/import', name: 'admin_api_seo_redirects_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        try {
            $payload = JsonPayload::fromRequest($request);
            $report = $this->importer->import(
                $payload->string('csv'),
                $payload->bool('dryRun', true),
                $payload->bool('updateExisting', false),
            );

            return new JsonResponse($report->toArray());
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Redirect API');
        }
    }

    #[Route('', name: 'admin_api_seo_redirects_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        try {
            $payload = JsonPayload::fromRequest($request);
            $change = $this->manager->create(
                $payload->string('sourcePath'),
                $payload->string('targetPath'),
                $payload->int('statusCode', 301),
                $payload->bool('isActive', true),
            );

            return new JsonResponse(self::changeToArray($change), 201);
        } catch (RedirectValidationException $exception) {
            return $this->validationFailed($exception);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Redirect API');
        }
    }

    #[Route('/{id}', name: 'admin_api_seo_redirects_update', requirements: ['id' => '[0-9A-Za-z]{26}'], methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        try {
            $redirect = $this->redirects->findById($id);
            if ($redirect === null) {
                return $this->errors->notFound('Redirect not found.');
            }

            $payload = JsonPayload::fromRequest($request);
            $change = $this->manager->update(
                $redirect,
                $payload->has('sourcePath') ? $payload->string('sourcePath') : $redirect->sourcePath(),
                $payload->string('targetPath'),
                $payload->int('statusCode', $redirect->statusCode()),
                $payload->bool('isActive', $redirect->isActive()),
            );

            return new JsonResponse(self::changeToArray($change));
        } catch (RedirectValidationException $exception) {
            return $this->validationFailed($exception);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Redirect API');
        }
    }

    #[Route('/{id}', name: 'admin_api_seo_redirects_delete', requirements: ['id' => '[0-9A-Za-z]{26}'], methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        try {
            $redirect = $this->redirects->findById($id);
            if ($redirect === null) {
                return $this->errors->notFound('Redirect not found.');
            }

            $this->manager->remove($redirect);

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Redirect API');
        }
    }

    private function validationFailed(RedirectValidationException $exception): JsonResponse
    {
        return $this->errors->validation(
            $exception->getMessage(),
            $exception->errorCode,
            [['field' => $exception->field, 'message' => $exception->getMessage()]],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function changeToArray(RedirectChange $change): array
    {
        return self::redirectToArray($change->redirect) + [
            'warnings' => array_map(static fn ($warning): array => $warning->toArray(), $change->warnings),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function redirectToArray(Redirect $redirect): array
    {
        return [
            'id' => (string) $redirect->id(),
            'sourcePath' => $redirect->sourcePath(),
            'targetPath' => $redirect->targetPath(),
            'statusCode' => $redirect->statusCode(),
            'isActive' => $redirect->isActive(),
            'hitCount' => $redirect->hitCount(),
            'lastHitAt' => $redirect->lastHitAt()?->format(DATE_ATOM),
            'updatedAt' => $redirect->updatedAt()->format(DATE_ATOM),
        ];
    }
}
