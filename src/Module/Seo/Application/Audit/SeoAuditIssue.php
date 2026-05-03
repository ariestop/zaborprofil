<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Audit;

final readonly class SeoAuditIssue
{
    public function __construct(
        public SeoAuditSeverity $severity,
        public string $code,
        public string $message,
        public string $field,
    ) {
    }

    /**
     * @return array{severity: string, code: string, message: string, field: string}
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'code' => $this->code,
            'message' => $this->message,
            'field' => $this->field,
        ];
    }
}
