<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Http;

use App\Module\Admin\Application\Service\DangerousActionTokenStoreInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class SessionDangerousActionTokenStore implements DangerousActionTokenStoreInterface
{
    private const string SESSION_KEY = 'system_dangerous_tokens';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function load(): array
    {
        $tokens = $this->session()->get(self::SESSION_KEY, []);

        return \is_array($tokens) ? $tokens : [];
    }

    public function save(array $tokens): void
    {
        $this->session()->set(self::SESSION_KEY, $tokens);
    }

    private function session(): SessionInterface
    {
        try {
            return $this->requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            throw new InvalidArgumentException('Session is not available for confirmation token.', previous: $exception);
        }
    }
}
