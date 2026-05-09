<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;
use InvalidArgumentException;

final class WhitelistCommandRunner
{
    /**
     * @var array<string, array{command: list<string>, timeoutSeconds: int}>
     */
    private const array ALLOWED_ACTIONS = [
        'process.restart.php-fpm' => [
            'command' => ['sudo', '-n', 'systemctl', 'restart', 'php-fpm'],
            'timeoutSeconds' => 30,
        ],
        'process.reload.nginx' => [
            'command' => ['sudo', '-n', 'systemctl', 'reload', 'nginx'],
            'timeoutSeconds' => 20,
        ],
        'cache.clear.app' => [
            'command' => ['php', 'bin/console', 'cache:clear', '--no-warmup'],
            'timeoutSeconds' => 120,
        ],
        'queue.retry.failed' => [
            'command' => ['php', 'bin/console', 'messenger:failed:retry', '--transport=failed', '--force'],
            'timeoutSeconds' => 60,
        ],
        'queue.remove.failed' => [
            'command' => ['php', 'bin/console', 'messenger:failed:remove', '--transport=failed', '--all', '--force'],
            'timeoutSeconds' => 60,
        ],
    ];

    public function __construct(
        private readonly string $projectDir,
        private readonly string $environment,
    ) {
    }

    /**
     * @return array{
     *   action: string,
     *   command: string,
     *   exitCode: int,
     *   output: string,
     *   startedAt: string,
     *   finishedAt: string
     * }
     */
    public function run(string $action): array
    {
        if (!isset(self::ALLOWED_ACTIONS[$action])) {
            throw new InvalidArgumentException('Requested action is not whitelisted.');
        }

        $definition = self::ALLOWED_ACTIONS[$action];
        $command = $definition['command'];
        $startedAt = new DateTimeImmutable();
        $output = [];
        $exitCode = 1;

        if ($this->environment === 'test') {
            return [
                'action' => $action,
                'command' => $this->buildCommandString($command),
                'exitCode' => 0,
                'output' => 'Simulated command execution in test environment.',
                'startedAt' => $startedAt->format(DATE_ATOM),
                'finishedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];
        }

        $cwd = getcwd();
        chdir($this->projectDir);

        // Intentionally executes only commands from fixed whitelist.
        exec($this->buildCommandString($command).' 2>&1', $output, $exitCode);

        if (\is_string($cwd) && $cwd !== '') {
            chdir($cwd);
        }

        return [
            'action' => $action,
            'command' => $this->buildCommandString($command),
            'exitCode' => $exitCode,
            'output' => trim(implode("\n", $output)),
            'startedAt' => $startedAt->format(DATE_ATOM),
            'finishedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @return list<string>
     */
    public function supportedActions(): array
    {
        return array_values(array_keys(self::ALLOWED_ACTIONS));
    }

    /**
     * @param list<string> $command
     */
    private function buildCommandString(array $command): string
    {
        return implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $command));
    }
}
