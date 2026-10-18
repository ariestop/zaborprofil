<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

/**
 * Хранилище выданных токенов подтверждения опасных действий текущего администратора (в его сессии).
 */
interface DangerousActionTokenStoreInterface
{
    /**
     * @return array<mixed> токен => данные токена
     *
     * @throws \InvalidArgumentException если хранилище недоступно (нет сессии)
     */
    public function load(): array;

    /**
     * @param array<mixed> $tokens
     *
     * @throws \InvalidArgumentException если хранилище недоступно (нет сессии)
     */
    public function save(array $tokens): void;
}
