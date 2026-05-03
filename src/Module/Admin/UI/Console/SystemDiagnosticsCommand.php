<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Console;

use App\Shared\Infrastructure\Health\HealthRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'app:system:diagnostics', description: 'Run system diagnostics for the CMS runtime.')]
final class SystemDiagnosticsCommand extends Command
{
    public function __construct(
        private readonly HealthRegistry $healthRegistry,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Zaborprofil CMS diagnostics');

        $rows = [];
        foreach ($this->healthRegistry->runAll() as $result) {
            $rows[] = [
                $result->label,
                strtoupper($result->status),
                $result->message,
            ];
        }

        foreach ($this->extensionChecks() as $extension => $loaded) {
            $rows[] = [
                'PHP extension: '.$extension,
                $loaded ? 'OK' : 'FAIL',
                $loaded ? 'Loaded' : 'Missing',
            ];
        }

        $rows[] = ['APP_ENV', strtoupper($this->kernel->getEnvironment()), 'Current Symfony environment'];
        $rows[] = ['APP_DEBUG', $this->kernel->isDebug() ? 'WARNING' : 'OK', $this->kernel->isDebug() ? 'Debug mode is enabled' : 'Debug mode is disabled'];
        $rows[] = ['Vite manifest', is_file($this->kernel->getProjectDir().'/public_html/build/.vite/manifest.json') ? 'OK' : 'WARNING', 'public_html/build/.vite/manifest.json'];

        $io->table(['Check', 'Status', 'Details'], $rows);

        foreach ($rows as $row) {
            if ($row[1] === 'FAIL') {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, bool>
     */
    private function extensionChecks(): array
    {
        return [
            'pdo_pgsql' => \extension_loaded('pdo_pgsql'),
            'intl' => \extension_loaded('intl'),
            'mbstring' => \extension_loaded('mbstring'),
            'imagick' => \extension_loaded('imagick'),
            'json' => \extension_loaded('json'),
        ];
    }
}
