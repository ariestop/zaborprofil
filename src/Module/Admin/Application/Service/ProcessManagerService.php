<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class ProcessManagerService
{
    public function __construct(
        private WhitelistCommandRunner $commandRunner,
        private KernelInterface $kernel,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return [
            'host' => gethostname() ?: 'unknown',
            'environment' => $this->kernel->getEnvironment(),
            'supportedActions' => [
                'restart' => ['php-fpm'],
                'reload' => ['nginx'],
            ],
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function restart(string $service): array
    {
        $action = match ($service) {
            'php-fpm' => 'process.restart.php-fpm',
            default => throw new \InvalidArgumentException('Unsupported service for restart.'),
        };

        return $this->commandRunner->run($action);
    }

    /**
     * @return array<string, mixed>
     */
    public function reload(string $service): array
    {
        $action = match ($service) {
            'nginx' => 'process.reload.nginx',
            default => throw new \InvalidArgumentException('Unsupported service for reload.'),
        };

        return $this->commandRunner->run($action);
    }
}
