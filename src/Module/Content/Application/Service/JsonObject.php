<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use stdClass;

/**
 * Контракт API: content и settings блока — всегда JSON-объект.
 * Пустой PHP-массив сериализуется json_encode как `[]`, поэтому на границе API он заменяется на `{}`.
 */
final class JsonObject
{
    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>|stdClass
     */
    public static function from(array $value): array|stdClass
    {
        return $value === [] ? new stdClass() : $value;
    }
}
