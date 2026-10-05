<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

use App\Module\Seo\Domain\Entity\Redirect;

final readonly class RedirectChange
{
    /**
     * @param list<RedirectWarning> $warnings
     */
    public function __construct(
        public Redirect $redirect,
        public array $warnings,
    ) {
    }
}
