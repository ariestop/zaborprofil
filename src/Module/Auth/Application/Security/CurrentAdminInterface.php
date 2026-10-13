<?php

declare(strict_types=1);

namespace App\Module\Auth\Application\Security;

/**
 * Кто выполняет действие: текущий администратор из сессии. Application-сервисы получают его через этот порт,
 * а не через Symfony Security (`docs/04-layer-rules.md`).
 */
interface CurrentAdminInterface
{
    /**
     * ULID администратора; `null` — действие вне сессии админки (CLI, публичный запрос, планировщик).
     */
    public function id(): ?string;

    /**
     * Идентификатор входа (email); `null`, если пользователь не вошёл.
     */
    public function identifier(): ?string;

    /**
     * @return list<string> роли без учёта иерархии (как в учётной записи)
     */
    public function roles(): array;
}
