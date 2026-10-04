<?php

declare(strict_types=1);

namespace App\Module\Lead\UI\Admin;

use App\Module\Admin\Application\Service\AdminAuditLogger;
use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\UI\Admin\JsonRequest;
use App\Module\Lead\Application\Export\LeadCsvExporter;
use App\Module\Lead\Application\Workflow\LeadWorkflow;
use App\Module\Lead\Domain\Repository\LeadAssigneeDirectoryInterface;
use App\Module\Lead\Domain\Repository\LeadEventRepositoryInterface;
use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use App\Module\Lead\Domain\Repository\LeadSearchCriteria;
use App\Module\Lead\Domain\ValueObject\LeadAssignee;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use App\Module\Lead\Infrastructure\Security\LeadActorProvider;
use App\Shared\Application\Logging\BusinessEventLogger;
use App\Shared\UI\Http\AdminApiErrorResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/leads')]
final readonly class LeadApiController
{
    public const int EXPORT_LIMIT = 10000;

    private const string ID_PATTERN = '[0-9A-Za-z]{26}';

    public function __construct(
        private LeadRepositoryInterface $leads,
        private LeadEventRepositoryInterface $events,
        private LeadAssigneeDirectoryInterface $assignees,
        private LeadWorkflow $workflow,
        private LeadCriteriaParser $criteriaParser,
        private LeadPresenter $presenter,
        private LeadCsvExporter $exporter,
        private LeadActorProvider $actors,
        private JsonRequest $jsonRequest,
        private AuthorizationCheckerInterface $authorizationChecker,
        private AdminAuditLogger $audit,
        private BusinessEventLogger $businessEvents,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('', name: 'admin_api_leads_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_VIEW)) {
            return $this->accessDenied();
        }

        try {
            $criteria = $this->criteriaParser->parse($request, $this->actors->current());
            $result = $this->leads->search($criteria);
            $byStatus = $this->leads->countByStatus($criteria);

            return new JsonResponse([
                'items' => $this->presenter->list($result->items),
                'total' => $result->total,
                'page' => $criteria->page,
                'perPage' => $criteria->perPage,
                'pages' => max(1, (int) ceil($result->total / $criteria->perPage)),
                'counts' => ['total' => array_sum($byStatus), 'byStatus' => $byStatus],
                'statuses' => LeadStatus::values(),
                'sources' => $this->leads->distinctSources(),
            ]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Lead API');
        }
    }

    #[Route('/summary', name: 'admin_api_leads_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_VIEW)) {
            return $this->accessDenied();
        }

        try {
            $byStatus = $this->leads->countByStatus(new LeadSearchCriteria());

            return new JsonResponse([
                'total' => array_sum($byStatus),
                'new' => $byStatus[LeadStatus::NEW],
                'byStatus' => $byStatus,
                'statuses' => LeadStatus::values(),
            ]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Lead API');
        }
    }

    #[Route('/assignees', name: 'admin_api_leads_assignees', methods: ['GET'])]
    public function assignees(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_VIEW)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse([
                'items' => array_map(
                    static fn (LeadAssignee $assignee): array => ['id' => $assignee->id, 'email' => $assignee->email],
                    $this->assignees->assignable(),
                ),
            ]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Lead API');
        }
    }

    #[Route('/export', name: 'admin_api_leads_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_EXPORT)) {
            return $this->accessDenied();
        }

        try {
            $criteria = $this->criteriaParser->parse($request, $this->actors->current());
            $leads = $this->leads->findForExport($criteria, self::EXPORT_LIMIT);
            $csv = $this->exporter->export($leads, $this->presenter->labels($leads));

            $filters = [
                'status' => $criteria->status,
                'source' => $criteria->source,
                'from' => $request->query->getString('from') ?: null,
                'to' => $request->query->getString('to') ?: null,
                'assignee' => $request->query->getString('assignee') ?: null,
                'hasQuery' => $criteria->query !== null,
            ];
            $this->audit->log('lead.exported', 'lead', null, [], ['count' => \count($leads), 'filters' => $filters]);
            $this->businessEvents->log('lead.exported', ['count' => \count($leads)]);

            $response = new Response($csv);
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', \sprintf('attachment; filename="leads-%s.csv"', date('Ymd-His')));
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            return $response;
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Lead API');
        }
    }

    #[Route('/{id}', name: 'admin_api_leads_show', requirements: ['id' => self::ID_PATTERN], methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_VIEW)) {
            return $this->accessDenied();
        }

        try {
            $lead = $this->leads->get($id);
            $this->audit->log('lead.viewed', 'lead', (string) $lead->id());

            return new JsonResponse($this->presenter->detail($lead, $this->events->findByLead($lead)));
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Lead API');
        }
    }

    #[Route('/{id}/status', name: 'admin_api_leads_status', methods: ['PATCH'])]
    public function status(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_MANAGE)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $lead = $this->workflow->changeStatus(
                $this->leads->get($id),
                $this->jsonRequest->string($payload, 'status'),
                $this->actors->current(),
            );

            return new JsonResponse($this->presenter->detail($lead, $this->events->findByLead($lead)));
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Lead API');
        }
    }

    #[Route('/{id}/assignee', name: 'admin_api_leads_assignee', methods: ['PATCH'])]
    public function assignee(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_MANAGE)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $lead = $this->workflow->assign(
                $this->leads->get($id),
                $this->jsonRequest->nullableString($payload, 'assigneeId'),
                $this->actors->current(),
            );

            return new JsonResponse($this->presenter->detail($lead, $this->events->findByLead($lead)));
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Lead API');
        }
    }

    #[Route('/{id}/notes', name: 'admin_api_leads_notes', methods: ['POST'])]
    public function note(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_MANAGE)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $lead = $this->leads->get($id);
            $this->workflow->addNote($lead, $this->jsonRequest->string($payload, 'text'), $this->actors->current());

            return new JsonResponse($this->presenter->detail($lead, $this->events->findByLead($lead)), 201);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Lead API');
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
