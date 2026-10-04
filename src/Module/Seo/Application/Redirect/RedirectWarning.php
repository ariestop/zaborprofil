<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

final readonly class RedirectWarning
{
    public function __construct(
        public string $code,
        public string $message,
    ) {
    }

    /**
     * @return array{code: string, message: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message];
    }
}
