<?php

declare(strict_types=1);

namespace App\Module\Portfolio\UI\Web;

use App\Module\Portfolio\Application\Service\PortfolioLikeService;
use App\Module\Portfolio\Domain\Exception\PortfolioLikeLimitException;
use App\Shared\UI\Http\AdminApiErrorResponder;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Лайки работ портфолио: счётчики для страницы и постановка/снятие лайка.
 * Страницы портфолио кэшируются целиком, поэтому счётчики и «мой лайк» приходят отдельным запросом и сами не кэшируются.
 */
final readonly class PortfolioLikeController
{
    public const string VISITOR_COOKIE = 'zp_visitor';

    public function __construct(
        private PortfolioLikeService $likes,
        #[Autowire(service: 'limiter.public_likes')]
        private RateLimiterFactoryInterface $limiter,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('/api/portfolio/likes', name: 'api_portfolio_likes', methods: ['GET'])]
    public function states(Request $request): JsonResponse
    {
        $ids = array_values(array_filter(explode(',', (string) $request->query->get('ids', '')), static fn (string $id): bool => $id !== ''));

        return self::private(new JsonResponse(['likes' => (object) $this->likes->states($ids, $this->visitor($request))]));
    }

    #[Route('/api/portfolio/likes/{id}', name: 'api_portfolio_like', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function like(string $id, Request $request): JsonResponse
    {
        try {
            if (!$this->sameOrigin($request)) {
                return self::private(new JsonResponse(['error' => 'Cross-site request.', 'code' => 'FORBIDDEN'], 403));
            }

            // Считается каждый запрос: частые нажатия и скрипты упираются в лимит раньше, чем в БД.
            $limit = $this->limiter->create($request->getClientIp() ?? 'unknown')->consume();
            if (!$limit->isAccepted()) {
                return self::private(new JsonResponse(
                    ['error' => 'Слишком много нажатий. Попробуйте позже.', 'code' => 'TOO_MANY_REQUESTS'],
                    429,
                    ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())],
                ));
            }

            $payload = $request->toArray();
            if (!\is_bool($payload['liked'] ?? null)) {
                return $this->errors->validation('Поле liked должно быть true или false.');
            }

            $visitor = $this->visitor($request);
            $isNewVisitor = $visitor === null;
            $visitor ??= PortfolioLikeService::newVisitorToken();

            try {
                $state = $this->likes->setLiked($id, $visitor, $request->getClientIp() ?? 'unknown', $payload['liked']);
            } catch (UniqueConstraintViolationException) {
                // Два одновременных нажатия одного посетителя: лайк уже стоит.
                $state = $this->likes->states([$id], $visitor)[$id] ?? ['count' => 0, 'liked' => false];
            }

            $response = self::private(new JsonResponse($state));
            if ($isNewVisitor) {
                $response->headers->setCookie(Cookie::create(self::VISITOR_COOKIE)
                    ->withValue($visitor)
                    ->withExpires(new DateTimeImmutable('+1 year'))
                    ->withPath('/')
                    ->withSecure($request->isSecure())
                    ->withHttpOnly(true)
                    ->withSameSite(Cookie::SAMESITE_LAX));
            }

            return $response;
        } catch (PortfolioLikeLimitException $exception) {
            return self::private(new JsonResponse(['error' => $exception->getMessage(), 'code' => 'LIKE_LIMIT'], 429));
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Portfolio likes');
        }
    }

    private function visitor(Request $request): ?string
    {
        $token = $request->cookies->get(self::VISITOR_COOKIE);

        return \is_string($token) && PortfolioLikeService::isVisitorToken($token) ? $token : null;
    }

    /**
     * Лайк ставит только скрипт нашего сайта: браузер всегда присылает Origin в POST из fetch(), чужой сайт не подделает его.
     */
    private function sameOrigin(Request $request): bool
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        if ($site !== null && $site !== 'same-origin') {
            return false;
        }
        $origin = $request->headers->get('Origin');

        return $origin !== null && strcasecmp((string) parse_url($origin, \PHP_URL_HOST), $request->getHost()) === 0;
    }

    private static function private(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
