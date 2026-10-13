<?php

declare(strict_types=1);

namespace App\Shared\Application\Http;

/**
 * Сведения о текущем HTTP-запросе для журналов и аудита. Вне HTTP-запроса (CLI, воркер) все значения `null`.
 */
interface RequestContextInterface
{
    /**
     * IP клиента по правилам доверенных прокси (`docs/27-config-and-env.md`).
     */
    public function clientIp(): ?string;

    public function userAgent(): ?string;

    public function requestId(): ?string;
}
