<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Maintenance;

use DateTimeImmutable;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class MaintenanceState
{
    private string $stateFile;

    public function __construct(KernelInterface $kernel)
    {
        $this->stateFile = $kernel->getProjectDir().'/var/maintenance.json';
    }

    /**
     * @param list<string> $allowedIps
     */
    public function enable(string $message = 'Сайт временно находится на техническом обслуживании.', array $allowedIps = []): void
    {
        $directory = dirname($this->stateFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->stateFile, json_encode([
            'enabled' => true,
            'message' => $message,
            'allowedIps' => array_values(array_unique(array_filter($allowedIps))),
            'enabledAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function disable(): void
    {
        if (is_file($this->stateFile)) {
            unlink($this->stateFile);
        }
    }

    public function isEnabled(): bool
    {
        return $this->payload()['enabled'];
    }

    public function message(): string
    {
        $message = $this->payload()['message'] ?? null;

        return \is_string($message) && $message !== '' ? $message : 'Сайт временно находится на техническом обслуживании.';
    }

    /**
     * @return list<string>
     */
    public function allowedIps(): array
    {
        return $this->payload()['allowedIps'];
    }

    public function isIpAllowed(?string $ip): bool
    {
        return $ip !== null && \in_array($ip, $this->allowedIps(), true);
    }

    /**
     * @return array{enabled: bool, message: string|null, allowedIps: list<string>, enabledAt: string|null}
     */
    public function payload(): array
    {
        if (!is_file($this->stateFile)) {
            return [
                'enabled' => false,
                'message' => null,
                'allowedIps' => [],
                'enabledAt' => null,
            ];
        }

        $decoded = json_decode((string) file_get_contents($this->stateFile), true);
        if (!\is_array($decoded)) {
            return [
                'enabled' => false,
                'message' => null,
                'allowedIps' => [],
                'enabledAt' => null,
            ];
        }

        $allowedIps = $decoded['allowedIps'] ?? [];

        return [
            'enabled' => ($decoded['enabled'] ?? false) === true,
            'message' => \is_string($decoded['message'] ?? null) ? $decoded['message'] : null,
            'allowedIps' => \is_array($allowedIps) ? array_values(array_filter($allowedIps, \is_string(...))) : [],
            'enabledAt' => \is_string($decoded['enabledAt'] ?? null) ? $decoded['enabledAt'] : null,
        ];
    }
}
