<?php

declare(strict_types=1);

namespace App\Shared\Application\Http;

/**
 * Данные клиента из запроса, которые use case сохраняет или учитывает (IP в согласии, антиспам).
 * Заполняет контроллер: Application не читает HTTP-запрос сам.
 */
final readonly class ClientInfo
{
    public function __construct(
        public ?string $ip,
        public ?string $userAgent,
    ) {
    }
}
