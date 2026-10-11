<?php

declare(strict_types=1);

namespace App\Module\Auth\Infrastructure\Security;

use App\Shared\UI\Http\AdminApiResponses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Что получает запрос в админку без действующей сессии.
 *
 * HTML-страницы перенаправляются на форму входа, как и раньше. Admin API отвечает 401 JSON: fetch и XHR
 * молча следуют редиректу, получают HTML формы входа со статусом 200, и SPA принимала его за успешный ответ.
 */
final readonly class AdminAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    private const string ADMIN_API_PATH_PREFIX = '/admin/api';
    private const string LOGIN_ROUTE = 'admin_login';

    public function __construct(private HttpUtils $httpUtils)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $path = $request->getPathInfo();
        if ($path === self::ADMIN_API_PATH_PREFIX || str_starts_with($path, self::ADMIN_API_PATH_PREFIX.'/')) {
            return AdminApiResponses::unauthorized();
        }

        return $this->httpUtils->createRedirectResponse($request, self::LOGIN_ROUTE);
    }
}
