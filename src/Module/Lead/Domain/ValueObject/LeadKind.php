<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\ValueObject;

/**
 * Определение заявки от юридического лица или ИП по названию в поле «Имя».
 */
final readonly class LeadKind
{
    public static function isCompany(string $name): bool
    {
        return preg_match('/(?<![\p{L}])(ООО|ИП|АО|ЗАО|ПАО|ОАО|ТОО)(?![\p{L}])/u', $name) === 1
            || preg_match('/индивидуальный предприниматель|общество с ограниченной/iu', $name) === 1;
    }
}
