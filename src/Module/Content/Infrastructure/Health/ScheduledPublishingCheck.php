<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Health;

use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\Infrastructure\Health\HealthCheckInterface;
use App\Shared\Infrastructure\Health\HealthCheckResult;
use Symfony\Component\Clock\ClockInterface;
use Throwable;

/**
 * Сигнализирует, что планировщик публикаций (`app:content:publish-scheduled`) не запускается:
 * запланированная операция просрочена больше чем на {@see self::GRACE_MINUTES} минут.
 */
final readonly class ScheduledPublishingCheck implements HealthCheckInterface
{
    private const int GRACE_MINUTES = 10;

    public function __construct(
        private PageRepositoryInterface $pages,
        private ClockInterface $clock,
    ) {
    }

    public function name(): string
    {
        return 'scheduled_publishing';
    }

    public function label(): string
    {
        return 'Scheduled publishing';
    }

    public function isRequiredForReadiness(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        try {
            $overdue = $this->pages->countOverdueSchedules($this->clock->now()->modify(\sprintf('-%d minutes', self::GRACE_MINUTES)));
        } catch (Throwable $exception) {
            return HealthCheckResult::warning($this->name(), $this->label(), 'Scheduled pages cannot be inspected.', [
                'error' => $exception->getMessage(),
            ]);
        }

        if ($overdue > 0) {
            return HealthCheckResult::warning(
                $this->name(),
                $this->label(),
                \sprintf('%d scheduled operation(s) are overdue; check that app:content:publish-scheduled runs every minute.', $overdue),
                ['overdue' => $overdue],
            );
        }

        return HealthCheckResult::ok($this->name(), $this->label(), 'No overdue scheduled publications.', ['overdue' => 0]);
    }
}
