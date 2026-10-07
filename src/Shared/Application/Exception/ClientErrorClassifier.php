<?php

declare(strict_types=1);

namespace App\Shared\Application\Exception;

use App\Shared\Domain\Exception\ClientSafeExceptionInterface;
use InvalidArgumentException;
use Throwable;
use ValueError;

/**
 * Решает, является ли исключение ошибкой входных данных, текст которой можно показать клиенту (422).
 *
 * `InvalidArgumentException` и `ValueError` из кода приложения и Symfony (например, невалидный ULID)
 * описывают неверный запрос. Те же типы из библиотек persistence (Doctrine `ORMInvalidArgumentException`
 * и т.п.) означают ошибку программы: их текст содержит внутренние детали, а сама ошибка должна попасть в лог.
 */
final class ClientErrorClassifier
{
    /**
     * @var list<string>
     */
    private const array INTERNAL_NAMESPACES = ['Doctrine\\'];

    private function __construct()
    {
    }

    public static function isValidationError(Throwable $exception): bool
    {
        if ($exception instanceof ClientSafeExceptionInterface) {
            return true;
        }

        if (!$exception instanceof InvalidArgumentException && !$exception instanceof ValueError) {
            return false;
        }

        return !array_any(
            self::INTERNAL_NAMESPACES,
            static fn (string $namespace): bool => str_starts_with($exception::class, $namespace),
        );
    }
}
