<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;

final readonly class DangerousActionConfirmationService
{
    private const int TTL_SECONDS = 300;

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return array{confirmToken: string, expiresAt: string}
     */
    public function issue(string $actorId, string $action): array
    {
        $token = (string) new Ulid();
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT'.self::TTL_SECONDS.'S'));
        $payload = [
            'actorId' => $actorId,
            'action' => $action,
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        ];
        $session = $this->requestStack->getSession();
        if ($session === null) {
            throw new InvalidArgumentException('Session is not available for confirmation token.');
        }

        $tokens = $session->get('system_dangerous_tokens', []);
        if (!\is_array($tokens)) {
            $tokens = [];
        }
        $tokens[$token] = $payload;
        $session->set('system_dangerous_tokens', $tokens);

        return [
            'confirmToken' => $token,
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        ];
    }

    public function consume(string $actorId, string $action, string $confirmToken): void
    {
        if (trim($confirmToken) === '') {
            throw new InvalidArgumentException('Missing confirmation token.');
        }

        $session = $this->requestStack->getSession();
        if ($session === null) {
            throw new InvalidArgumentException('Session is not available for confirmation token.');
        }

        $tokens = $session->get('system_dangerous_tokens', []);
        if (!\is_array($tokens) || !isset($tokens[$confirmToken]) || !\is_array($tokens[$confirmToken])) {
            throw new InvalidArgumentException('Confirmation token is invalid or expired.');
        }
        $payload = $tokens[$confirmToken];
        unset($tokens[$confirmToken]);
        $session->set('system_dangerous_tokens', $tokens);

        $expiresAt = isset($payload['expiresAt']) && \is_string($payload['expiresAt']) ? strtotime($payload['expiresAt']) : false;
        if ($expiresAt === false || $expiresAt < time()) {
            throw new InvalidArgumentException('Confirmation token is invalid or expired.');
        }

        if (($payload['actorId'] ?? null) !== $actorId || ($payload['action'] ?? null) !== $action) {
            throw new InvalidArgumentException('Confirmation token does not match actor or action.');
        }
    }

}
