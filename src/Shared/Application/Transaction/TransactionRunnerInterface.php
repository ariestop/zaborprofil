<?php

declare(strict_types=1);

namespace App\Shared\Application\Transaction;

/**
 * Граница транзакции для use case, который сохраняет несколько сущностей:
 * либо фиксируются все изменения, либо ни одно (docs/17-doctrine-and-database.md §7.1).
 */
interface TransactionRunnerInterface
{
    /**
     * Выполняет операцию в транзакции БД. Вложенный вызов становится частью внешней транзакции.
     * Исключение операции откатывает транзакцию и пробрасывается дальше.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(callable $operation): mixed;

    /**
     * Действие после фиксации внешней транзакции (сброс кэша, внешние вызовы): при откате не выполняется.
     * Вне транзакции выполняется сразу.
     *
     * @param callable(): void $callback
     */
    public function afterCommit(callable $callback): void;
}
