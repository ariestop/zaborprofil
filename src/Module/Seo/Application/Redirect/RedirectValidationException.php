<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

use App\Shared\Domain\Exception\ClientSafeExceptionInterface;
use InvalidArgumentException;

final class RedirectValidationException extends InvalidArgumentException implements ClientSafeExceptionInterface
{
    public const string CODE_VALIDATION = 'VALIDATION';
    public const string CODE_LOOP = 'REDIRECT_LOOP';
    public const string CODE_DUPLICATE = 'REDIRECT_DUPLICATE';

    public function __construct(
        string $message,
        public readonly string $field,
        public readonly string $errorCode = self::CODE_VALIDATION,
    ) {
        parent::__construct($message);
    }
}
