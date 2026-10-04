<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Console;

use App\Module\Seo\Domain\Repository\NotFoundLogRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seo:not-found:prune', description: 'Delete 404 journal entries not seen for the given number of days.')]
final class NotFoundPruneCommand extends Command
{
    public function __construct(private readonly NotFoundLogRepositoryInterface $log)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Remove entries whose last hit is older than N days.', '90');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT);
        if (!\is_int($days) || $days < 1) {
            $io->error('Option --days must be a positive integer.');

            return Command::INVALID;
        }

        $removed = $this->log->pruneOlderThan(new DateTimeImmutable(\sprintf('-%d days', $days)));
        $io->success(\sprintf('Removed %d stale 404 entries.', $removed));

        return Command::SUCCESS;
    }
}
