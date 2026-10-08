<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Application\Transaction\TransactionRunnerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * Транзакция на уровне соединения с flush перед фиксацией.
 *
 * В отличие от EntityManager::wrapInTransaction() исключение операции не закрывает EntityManager:
 * пакетные операции и планировщик продолжают работу со следующим элементом после ошибки валидации
 * в предыдущем. Ошибка самого flush по-прежнему закрывает EntityManager (так устроен Doctrine UnitOfWork).
 * Вложенные вызовы DBAL оформляет точками сохранения (SAVEPOINT).
 */
final class DoctrineTransactionRunner implements TransactionRunnerInterface, ResetInterface
{
    private int $depth = 0;

    /**
     * @var list<callable(): void>
     */
    private array $afterCommit = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function run(callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        ++$this->depth;

        try {
            $result = $operation();
            $this->entityManager->flush();
            $connection->commit();
        } catch (Throwable $exception) {
            --$this->depth;
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            if ($this->depth === 0) {
                $this->afterCommit = [];
            }

            throw $exception;
        }

        --$this->depth;
        if ($this->depth === 0) {
            $callbacks = $this->afterCommit;
            $this->afterCommit = [];
            foreach ($callbacks as $callback) {
                $callback();
            }
        }

        return $result;
    }

    public function afterCommit(callable $callback): void
    {
        if ($this->depth === 0) {
            $callback();

            return;
        }

        $this->afterCommit[] = $callback;
    }

    public function reset(): void
    {
        $this->depth = 0;
        $this->afterCommit = [];
    }
}
