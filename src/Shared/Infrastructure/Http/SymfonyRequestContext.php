<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Application\Http\RequestContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SymfonyRequestContext implements RequestContextInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function clientIp(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getClientIp();
    }

    public function userAgent(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->headers->get('User-Agent');
    }

    public function requestId(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->attributes->getString(RequestIdSubscriber::ATTRIBUTE) ?: null;
    }
}
