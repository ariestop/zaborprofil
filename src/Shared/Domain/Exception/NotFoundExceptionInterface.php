<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use Throwable;

/**
 * Доменная ошибка «объект не найден»: текст безопасен для показа клиенту API.
 */
interface NotFoundExceptionInterface extends Throwable
{
}
