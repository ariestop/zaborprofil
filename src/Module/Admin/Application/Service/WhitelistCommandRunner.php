<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class WhitelistCommandRunner
{
    private const int TIMEOUT_EXIT_CODE = 124;

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
        private string $projectDir,
        private string $environment,
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

        // Только команды из фиксированного списка. `timeout` (coreutils) обрывает зависшую команду:
        // иначе запрос админки держит воркер PHP-FPM до max_execution_time. Код 124 — команда прервана по таймауту.
        exec($this->buildCommandString(['timeout', (string) $definition['timeoutSeconds'], ...$command]).' 2>&1', $output, $exitCode);
        if ($exitCode === self::TIMEOUT_EXIT_CODE) {
            $output[] = \sprintf('Command timed out after %d seconds.', $definition['timeoutSeconds']);
        }

        if ($cwd !== false) {
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
        return array_keys(self::ALLOWED_ACTIONS);
    }

    /**
     * @param list<string> $command
     */
    private function buildCommandString(array $command): string
    {
        return implode(' ', array_map(escapeshellarg(...), $command));
    }
}
