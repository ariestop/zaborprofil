<?php

declare(strict_types=1);

namespace App\Module\User\Domain\Exception;

use RuntimeException;

final class UserManagementException extends RuntimeException
{
    public const string VALIDATION = 'VALIDATION_FAILED';
    public const string CONFLICT = 'USER_ALREADY_EXISTS';
    public const string SELF_LOCKOUT = 'SELF_LOCKOUT';
    public const string ROLE_NOT_ALLOWED = 'ROLE_NOT_ALLOWED';
    public const string LAST_ADMIN = 'LAST_ADMIN';
    public const string INVALID_CURRENT_PASSWORD = 'INVALID_CURRENT_PASSWORD';

    /**
     * @param list<array{field: string, message: string}> $details
     */
    private function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<array{field: string, message: string}> $details
     */
    public static function validation(string $message, array $details): self
    {
        return new self(self::VALIDATION, $message, $details);
    }

    public static function conflict(string $email): self
    {
        return new self(self::CONFLICT, \sprintf('Пользователь %s уже существует.', $email));
    }

    public static function selfLockout(string $message): self
    {
        return new self(self::SELF_LOCKOUT, $message);
    }

    public static function roleNotAllowed(string $message): self
    {
        return new self(self::ROLE_NOT_ALLOWED, $message);
    }

    public static function lastAdmin(string $message): self
    {
        return new self(self::LAST_ADMIN, $message);
    }

    public static function invalidCurrentPassword(): self
    {
        return new self(self::INVALID_CURRENT_PASSWORD, 'Текущий пароль указан неверно.', [
            ['field' => 'currentPassword', 'message' => 'Текущий пароль указан неверно.'],
        ]);
    }
}
