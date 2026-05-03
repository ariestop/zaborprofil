<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use App\Shared\Infrastructure\Health\HealthRegistry;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class SystemWarningCollector
{
    public function __construct(
        private HealthRegistry $healthRegistry,
        private KernelInterface $kernel,
    ) {
    }

    /**
     * @return list<array{code: string, severity: string, message: string}>
     */
    public function collect(): array
    {
        $warnings = [];

        if ($this->kernel->getEnvironment() === 'prod' && $this->kernel->isDebug()) {
            $warnings[] = [
                'code' => 'prod_debug_enabled',
                'severity' => 'critical',
                'message' => 'APP_DEBUG включен в production.',
            ];
        }

        foreach ($this->healthRegistry->runAll() as $result) {
            if ($result->status === 'fail') {
                $warnings[] = [
                    'code' => 'health_'.$result->name.'_failed',
                    'severity' => 'critical',
                    'message' => $result->label.': '.$result->message,
                ];
            }

            if ($result->status === 'warning') {
                $warnings[] = [
                    'code' => 'health_'.$result->name.'_warning',
                    'severity' => 'warning',
                    'message' => $result->label.': '.$result->message,
                ];
            }
        }

        return $warnings;
    }
}
