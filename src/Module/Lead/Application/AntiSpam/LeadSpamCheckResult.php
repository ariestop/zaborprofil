<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\AntiSpam;

final readonly class LeadSpamCheckResult
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public int $score,
        public array $reasons,
    ) {
    }

    public function isSpam(): bool
    {
        return $this->score >= 100;
    }
}
