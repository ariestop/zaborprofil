<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\Notification;

final readonly class LeadNotificationResult
{
    public const string CHANNEL_EMAIL = 'email';
    public const string CHANNEL_TELEGRAM = 'telegram';

    /**
     * @param list<string> $delivered
     * @param list<string> $failed
     */
    public function __construct(
        public array $delivered,
        public array $failed,
    ) {
    }

    public function isDelivered(): bool
    {
        return $this->delivered !== [];
    }

    public function isNotConfigured(): bool
    {
        return $this->delivered === [] && $this->failed === [];
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
