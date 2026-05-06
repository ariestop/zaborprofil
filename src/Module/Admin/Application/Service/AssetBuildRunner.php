<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use App\Kernel;
use DateTimeImmutable;
use RuntimeException;

final readonly class AssetBuildRunner
{
    private const string DEFAULT_COMMAND = 'npm run build';
    private const int MAX_LOG_BYTES = 40000;

    private string $projectDir;
    private string $command;
    private string $stateDir;
    private string $statusPath;
    private string $logPath;
    private string $scriptPath;

    public function __construct(Kernel $kernel, string $command = self::DEFAULT_COMMAND)
    {
        $projectDirResolver = [$kernel, 'getProjectDir'];
        if (!is_callable($projectDirResolver)) {
            throw new RuntimeException('Kernel should provide project directory.');
        }

        $this->projectDir = (string) $projectDirResolver();
        $this->command = trim($command) !== '' ? trim($command) : self::DEFAULT_COMMAND;
        $this->stateDir = $this->projectDir . '/var/admin-build';
        $this->statusPath = $this->stateDir . '/status.json';
        $this->logPath = $this->stateDir . '/build.log';
        $this->scriptPath = $this->stateDir . '/run-build.sh';
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $status = $this->readStatus();
        $logs = $this->readLogs();

        if (($status['status'] ?? 'idle') === 'running' && !$this->isCurrentProcessRunning($status)) {
            $status['status'] = 'failed';
            $status['finishedAt'] ??= (new DateTimeImmutable())->format(DATE_ATOM);
            $status['exitCode'] ??= null;
            $logs = trim($logs . "\n\nПроцесс сборки завершился без финального статуса.");
            file_put_contents($this->logPath, $logs . "\n", \FILE_APPEND | \LOCK_EX);
            $this->writeStatus($status);
        }

        return $this->withRuntimeFields($status, $logs);
    }

    /**
     * @return array<string, mixed>
     */
    public function start(): array
    {
        $currentStatus = $this->status();
        if ($currentStatus['status'] === 'running') {
            return $currentStatus;
        }

        $this->ensureStateDir();

        $startedAt = (new DateTimeImmutable())->format(DATE_ATOM);
        $initialStatus = [
            'status' => 'running',
            'command' => $this->command,
            'startedAt' => $startedAt,
            'finishedAt' => null,
            'exitCode' => null,
            'pid' => null,
        ];

        file_put_contents($this->logPath, \sprintf("Запуск %s...\n", $this->command), \LOCK_EX);
        $this->writeStatus($initialStatus);
        $this->writeRunnerScript($startedAt);

        $pid = $this->startBackgroundProcess();
        if ($pid <= 0) {
            $failedStatus = [
                ...$initialStatus,
                'status' => 'failed',
                'finishedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];
            $this->writeStatus($failedStatus);
            file_put_contents($this->logPath, "Не удалось запустить фоновый процесс сборки.\n", \FILE_APPEND | \LOCK_EX);

            return $this->status();
        }

        $statusAfterStart = $this->readStatus();
        if (($statusAfterStart['status'] ?? 'idle') === 'running') {
            $statusAfterStart['pid'] = $pid;
            $this->writeStatus($statusAfterStart);
        }

        return $this->status();
    }

    private function ensureStateDir(): void
    {
        if (is_dir($this->stateDir)) {
            return;
        }

        if (!mkdir($concurrentDirectory = $this->stateDir, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Unable to create admin build state directory.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readStatus(): array
    {
        if (!is_file($this->statusPath)) {
            return [
                'status' => 'idle',
                'command' => $this->command,
                'startedAt' => null,
                'finishedAt' => null,
                'exitCode' => null,
                'pid' => null,
            ];
        }

        $decoded = json_decode((string) file_get_contents($this->statusPath), true);
        if (!\is_array($decoded)) {
            return [
                'status' => 'idle',
                'command' => $this->command,
                'startedAt' => null,
                'finishedAt' => null,
                'exitCode' => null,
                'pid' => null,
            ];
        }

        $status = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $status[$key] = $value;
            }
        }

        return $status;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function writeStatus(array $status): void
    {
        $this->ensureStateDir();

        $encoded = json_encode($status, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
        file_put_contents($this->statusPath, $encoded . "\n", \LOCK_EX);
    }

    private function readLogs(): string
    {
        if (!is_file($this->logPath)) {
            return '';
        }

        $size = filesize($this->logPath);
        if ($size === false || $size <= self::MAX_LOG_BYTES) {
            return (string) file_get_contents($this->logPath);
        }

        $handle = fopen($this->logPath, 'rb');
        if ($handle === false) {
            return '';
        }

        fseek($handle, -self::MAX_LOG_BYTES, \SEEK_END);
        $logs = stream_get_contents($handle);
        fclose($handle);

        return '[...лог обрезан до последних ' . self::MAX_LOG_BYTES . " байт...]\n" . (string) $logs;
    }

    private function writeRunnerScript(string $startedAt): void
    {
        $script = <<<'SH'
#!/bin/sh
set +e

export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:$PATH"
export HOME=__STATE_DIR__
export npm_config_cache=__NPM_CACHE_DIR__
cd __PROJECT_DIR__ || exit 1
mkdir -p "$npm_config_cache"

if [ "__REQUIRES_NPM__" = "1" ] && ! command -v npm >/dev/null 2>&1; then
  echo "npm не найден в PATH: $PATH" >> __LOG_PATH__
  echo "Установите Node.js/npm в runtime PHP-FPM или задайте APP_ASSET_BUILD_COMMAND." >> __LOG_PATH__
  exit_code=127
else
  if [ "__REQUIRES_NPM__" = "1" ] && [ -f package-lock.json ]; then
    echo "Переустановка npm dependencies под текущий контейнер..." >> __LOG_PATH__
    npm ci --include=optional --no-audit --no-fund >> __LOG_PATH__ 2>&1
    install_exit_code=$?
  elif [ "__REQUIRES_NPM__" = "1" ]; then
    echo "Установка npm dependencies..." >> __LOG_PATH__
    npm install --include=optional --no-audit --no-fund >> __LOG_PATH__ 2>&1
    install_exit_code=$?
  else
    install_exit_code=0
  fi

  if [ "$install_exit_code" -ne 0 ]; then
    echo "Установка npm dependencies завершилась с ошибкой $install_exit_code. Сборка остановлена." >> __LOG_PATH__
    exit_code=$install_exit_code
  else
    __BUILD_COMMAND__ >> __LOG_PATH__ 2>&1
    exit_code=$?
  fi
fi

finished_at=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

if [ "$exit_code" -eq 0 ]; then
  state="success"
else
  state="failed"
fi

cat > __STATUS_TMP_PATH__ <<EOF
{"status":"$state","command":"__STATUS_COMMAND__","startedAt":"__STARTED_AT__","finishedAt":"$finished_at","exitCode":$exit_code,"pid":null}
EOF
mv __STATUS_TMP_PATH__ __STATUS_PATH__
exit "$exit_code"
SH;

        $script = strtr($script, [
            '__PROJECT_DIR__' => escapeshellarg($this->projectDir),
            '__STATE_DIR__' => escapeshellarg($this->stateDir),
            '__NPM_CACHE_DIR__' => escapeshellarg($this->stateDir . '/npm-cache'),
            '__LOG_PATH__' => escapeshellarg($this->logPath),
            '__BUILD_COMMAND__' => $this->command,
            '__REQUIRES_NPM__' => str_starts_with($this->command, 'npm') ? '1' : '0',
            '__STATUS_COMMAND__' => addcslashes($this->command, "\\\"\n\r\t"),
            '__STATUS_TMP_PATH__' => escapeshellarg($this->statusPath . '.tmp'),
            '__STATUS_PATH__' => escapeshellarg($this->statusPath),
            '__STARTED_AT__' => $startedAt,
        ]);

        file_put_contents($this->scriptPath, $script, \LOCK_EX);
        chmod($this->scriptPath, 0755);
    }

    private function startBackgroundProcess(): int
    {
        $output = [];
        exec('/bin/sh ' . escapeshellarg($this->scriptPath) . ' > /dev/null 2>&1 & echo $!', $output);

        return isset($output[0]) ? (int) $output[0] : 0;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function isCurrentProcessRunning(array $status): bool
    {
        $pid = $status['pid'] ?? null;
        if (!\is_int($pid) || $pid <= 0) {
            $startedAt = \is_string($status['startedAt'] ?? null) ? strtotime($status['startedAt']) : false;

            return $startedAt !== false && time() - $startedAt < 10;
        }

        $output = [];
        $exitCode = 1;
        exec('kill -0 ' . $pid . ' 2>/dev/null', $output, $exitCode);

        return $exitCode === 0;
    }

    /**
     * @param array<string, mixed> $status
     *
     * @return array<string, mixed>
     */
    private function withRuntimeFields(array $status, string $logs): array
    {
        $state = \is_string($status['status'] ?? null) ? $status['status'] : 'idle';

        return [
            'status' => \in_array($state, ['idle', 'running', 'success', 'failed'], true) ? $state : 'idle',
            'command' => $this->command,
            'startedAt' => $status['startedAt'] ?? null,
            'finishedAt' => $status['finishedAt'] ?? null,
            'exitCode' => $status['exitCode'] ?? null,
            'progress' => $this->progress($state, $logs),
            'logs' => $logs,
        ];
    }

    private function progress(string $state, string $logs): int
    {
        if ($state === 'success') {
            return 100;
        }

        if ($state === 'failed') {
            return 100;
        }

        if ($state !== 'running') {
            return 0;
        }

        $progress = 15;
        foreach ([
            'tsc --noEmit' => 30,
            'vite build' => 55,
            'transforming' => 70,
            'rendering chunks' => 82,
            'computing gzip size' => 92,
        ] as $needle => $stepProgress) {
            if (str_contains($logs, $needle)) {
                $progress = $stepProgress;
            }
        }

        return $progress;
    }
}
