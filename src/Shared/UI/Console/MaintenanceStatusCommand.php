<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use App\Shared\Infrastructure\Maintenance\MaintenanceState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:maintenance:status', description: 'Show maintenance mode status.')]
final class MaintenanceStatusCommand extends Command
{
    public function __construct(private readonly MaintenanceState $maintenance)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $payload = $this->maintenance->payload();

        $io->table(['Field', 'Value'], [
            ['enabled', $payload['enabled'] ? 'yes' : 'no'],
            ['message', $payload['message'] ?? ''],
            ['allowedIps', implode(', ', $payload['allowedIps'])],
            ['enabledAt', $payload['enabledAt'] ?? ''],
        ]);

        return Command::SUCCESS;
    }
}
