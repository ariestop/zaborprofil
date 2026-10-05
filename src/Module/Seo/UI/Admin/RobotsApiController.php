<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Seo\Application\Robots\RobotsTxtIssue;
use App\Module\Seo\Application\Robots\RobotsTxtValidator;
use App\Module\Seo\Application\Service\RobotsTxtManager;
use App\Module\Settings\Application\Service\SettingsService;
use App\Shared\UI\Http\AdminApiErrorResponder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/seo/robots')]
final readonly class RobotsApiController
{
    public function __construct(
        private RobotsTxtManager $robots,
        private RobotsTxtValidator $validator,
        private SettingsService $settings,
        private AuthorizationCheckerInterface $authorizationChecker,
        private PublicPageCacheInvalidator $publicPageCache,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('', name: 'admin_api_seo_robots_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        return $this->current();
    }

    #[Route('/preview', name: 'admin_api_seo_robots_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        try {
            $body = JsonPayload::fromRequest($request)->nullableString('body');
            $issues = [];
            $normalized = null;
            try {
                $normalized = $this->robots->normalizeEditableBody($body);
            } catch (InvalidArgumentException $exception) {
                $issues[] = new RobotsTxtIssue(RobotsTxtIssue::ERROR, null, $exception->getMessage());
            }

            if ($normalized !== null) {
                $issues = [...$issues, ...$this->validator->validate($normalized)];
            }

            return new JsonResponse([
                'normalizedBody' => $normalized,
                'effectiveBody' => $this->robots->bodyFor($normalized),
                'usesDefault' => $normalized === null,
                'overriddenByEnvironment' => !$this->robots->isProduction(),
                'valid' => !array_any($issues, static fn (RobotsTxtIssue $issue): bool => $issue->isError()),
                'issues' => array_map(static fn (RobotsTxtIssue $issue): array => $issue->toArray(), $issues),
            ]);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Robots API');
        }
    }

    #[Route('', name: 'admin_api_seo_robots_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return AccessDeniedResponse::create();
        }

        try {
            $body = JsonPayload::fromRequest($request)->nullableString('body');
            $normalized = $this->robots->normalizeEditableBody($body);
            if ($normalized !== null) {
                $details = [];
                foreach ($this->validator->validate($normalized) as $issue) {
                    if ($issue->isError()) {
                        $details[] = [
                            'field' => 'body',
                            'message' => $issue->line === null ? $issue->message : \sprintf('Строка %d: %s', $issue->line, $issue->message),
                        ];
                    }
                }

                if ($details !== []) {
                    return $this->errors->validation('robots.txt содержит ошибки: '.$details[0]['message'], details: $details);
                }
            }

            if ($normalized === null) {
                $this->settings->delete(RobotsTxtManager::SCOPE, RobotsTxtManager::KEY);
            } else {
                $this->settings->set(RobotsTxtManager::SCOPE, RobotsTxtManager::KEY, $normalized, 'Custom robots.txt content.');
            }
            $this->publicPageCache->invalidateAll();

            return $this->current();
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin Robots API');
        }
    }

    private function current(): JsonResponse
    {
        $body = $this->robots->editableBody();

        return new JsonResponse([
            'body' => $body,
            'effectiveBody' => $this->robots->body(),
            'defaultBody' => $this->robots->defaultBody(),
            'environment' => $this->robots->environment(),
            'overriddenByEnvironment' => !$this->robots->isProduction(),
            'scope' => RobotsTxtManager::SCOPE,
            'key' => RobotsTxtManager::KEY,
        ]);
    }
}
