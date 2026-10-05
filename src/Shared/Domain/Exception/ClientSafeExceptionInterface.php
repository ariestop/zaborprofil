<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use Throwable;

/**
 * Ошибка валидации/бизнес-правила: текст написан для пользователя и безопасен для показа в ответе API.
 */
interface ClientSafeExceptionInterface extends Throwable
{
}
