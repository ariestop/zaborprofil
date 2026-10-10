<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\ValueObject;

final readonly class LeadAssignee
{
    public function __construct(
        public string $id,
        public string $email,
        public ?string $name = null,
    ) {
    }

    /**
     * Как показывать сотрудника в интерфейсе: «Фамилия Имя» из профиля, без него — email.
     */
    public function label(): string
    {
        return $this->name ?? $this->email;
    }
}
