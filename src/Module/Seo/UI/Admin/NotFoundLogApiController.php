<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Seo\Domain\Entity\NotFoundLogEntry;
use App\Module\Seo\Domain\Repository\NotFoundLogRepositoryInterface;
use App\Module\Seo\Domain\Repository\NotFoundSearchCriteria;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use App\Shared\UI\Http\AdminApiErrorResponder;
use App\Shared\UI\Http\AdminApiResponses;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/seo/not-found')]
final readonly class NotFoundLogApiController
{
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private NotFoundLogRepositoryInterface $log,
        private RedirectRepositoryInterface $redirects,
        private AuthorizationCheckerInterface $authorizationChecker,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('', name: 'admin_api_seo_not_found_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $page = max(1, $request->query->getInt('page', 1));
            $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('perPage', 25)));
            $sort = $request->query->getString('sort', NotFoundSearchCriteria::SORT_HITS);
            $result = $this->log->search(new NotFoundSearchCriteria(
                $request->query->getString('q') ?: null,
                \in_array($sort, NotFoundSearchCriteria::SORTS, true) ? $sort : NotFoundSearchCriteria::SORT_HITS,
                $page,
                $perPage,
            ));

            $covered = array_flip($this->redirects->findActiveSourcePaths(array_map(
                static fn (NotFoundLogEntry $entry): string => $entry->path(),
                $result->items,
            )));

            return new JsonResponse([
                'items' => array_map(
                    static fn (NotFoundLogEntry $entry): array => [
                        'id' => (string) $entry->id(),
                        'path' => $entry->path(),
                        'hitCount' => $entry->hitCount(),
                        'firstSeenAt' => $entry->firstSeenAt()->format(DATE_ATOM),
                        'lastSeenAt' => $entry->lastSeenAt()->format(DATE_ATOM),
                        'referrer' => $entry->referrer(),
                        'hasRedirect' => isset($covered[$entry->path()]),
                    ],
                    $result->items,
                ),
                'total' => $result->total,
                'totalHits' => $result->totalHits,
                'page' => $page,
                'perPage' => $perPage,
                'pages' => max(1, (int) ceil($result->total / $perPage)),
            ]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin 404 log API');
        }
    }

    #[Route('/{id}', name: 'admin_api_seo_not_found_delete', requirements: ['id' => '[0-9A-Za-z]{26}'], methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            $entry = $this->log->findById($id);
            if ($entry === null) {
                return $this->errors->notFound('404 log entry not found.');
            }

            $this->log->remove($entry);

            return new JsonResponse(null, 204);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin 404 log API');
        }
    }

    #[Route('', name: 'admin_api_seo_not_found_clear', methods: ['DELETE'])]
    public function clear(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        try {
            if ($request->query->has('olderThanDays')) {
                $days = filter_var($request->query->get('olderThanDays'), FILTER_VALIDATE_INT);
                if (!\is_int($days) || $days < 1) {
                    return $this->errors->validation('Parameter "olderThanDays" must be a positive integer.');
                }

                return new JsonResponse(['removed' => $this->log->pruneOlderThan(new DateTimeImmutable(\sprintf('-%d days', $days)))]);
            }

            return new JsonResponse(['removed' => $this->log->clear()]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin 404 log API');
        }
    }
}
