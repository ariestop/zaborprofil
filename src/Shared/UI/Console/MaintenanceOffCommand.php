<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use App\Shared\Infrastructure\Maintenance\MaintenanceState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:maintenance:off', description: 'Disable maintenance mode.')]
final class MaintenanceOffCommand extends Command
{
    public function __construct(private readonly MaintenanceState $maintenance)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenance->disable();

        (new SymfonyStyle($input, $output))->success('Maintenance mode disabled.');

        return Command::SUCCESS;
    }
}
