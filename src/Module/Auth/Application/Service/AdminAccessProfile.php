<?php

declare(strict_types=1);

namespace App\Module\Auth\Application\Service;

use App\Module\Auth\Application\Security\CurrentAdminInterface;
use App\Module\Auth\Domain\Security\AdminPermission;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Профиль доступа текущего администратора для SPA: роли и права, вычисленные теми же
 * voter'ами, что защищают эндпоинты. Фронтенд использует его только для скрытия недоступного UI —
 * сервер всё равно проверяет право на каждый запрос.
 */
final readonly class AdminAccessProfile
{
    public function __construct(
        private CurrentAdminInterface $currentAdmin,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @return array{email: string, roles: list<string>, permissions: list<string>}
     */
    public function current(): array
    {
        $identifier = $this->currentAdmin->identifier();
        if ($identifier === null) {
            return ['email' => '', 'roles' => [], 'permissions' => []];
        }

        return [
            'email' => $identifier,
            'roles' => $this->currentAdmin->roles(),
            'permissions' => array_values(array_filter(
                AdminPermission::all(),
                fn (string $permission): bool => $this->authorizationChecker->isGranted($permission),
            )),
        ];
    }
}
