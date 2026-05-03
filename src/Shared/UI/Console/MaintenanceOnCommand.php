<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use App\Shared\Infrastructure\Maintenance\MaintenanceState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:maintenance:on', description: 'Enable maintenance mode.')]
final class MaintenanceOnCommand extends Command
{
    public function __construct(private readonly MaintenanceState $maintenance)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Maintenance message.', 'Сайт временно находится на техническом обслуживании.')
            ->addOption('allow-ip', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'IP address allowed during maintenance.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $message = $input->getOption('message');
        $allowedIps = $input->getOption('allow-ip');

        $this->maintenance->enable(
            \is_string($message) ? $message : 'Сайт временно находится на техническом обслуживании.',
            array_values(array_filter(\is_array($allowedIps) ? $allowedIps : [], \is_string(...))),
        );

        $io->success('Maintenance mode enabled.');

        return Command::SUCCESS;
    }
}
