<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Console;

use App\Module\Content\Application\Service\ScheduledPagePublisher;
use App\Module\Content\Application\Service\ScheduledPublishingReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:content:publish-scheduled',
    description: 'Publish and unpublish pages whose scheduled date has come. Safe to run repeatedly (cron / systemd timer).',
)]
final class PublishScheduledPagesCommand extends Command
{
    public function __construct(
        private readonly ScheduledPagePublisher $publisher,
        #[Autowire('%kernel.project_dir%/var/lock/publish-scheduled.lock')]
        private readonly string $lockFile,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only list the pages that would be processed.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of pages per direction (publish / unpublish) per run.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if (!\is_int($limit) || $limit < 1 || $limit > 1000) {
            $io->error('Option --limit must be an integer between 1 and 1000.');

            return Command::INVALID;
        }

        $lock = $this->acquireLock();
        if ($lock === null) {
            $io->warning('Another publish-scheduled run is in progress; skipping.');

            return Command::SUCCESS;
        }

        try {
            $report = $this->publisher->run($limit, (bool) $input->getOption('dry-run'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $this->render($io, $report);

        return $report->hasProblems() ? Command::FAILURE : Command::SUCCESS;
    }

    private function render(SymfonyStyle $io, ScheduledPublishingReport $report): void
    {
        if ($report->items() === []) {
            $io->writeln('Nothing to do.');

            return;
        }

        $io->table(
            ['Page', 'Path', 'Outcome', 'Message'],
            array_map(static fn (array $item): array => [$item['pageId'], $item['path'], $item['outcome'], $item['message'] ?? ''], $report->items()),
        );

        $summary = \sprintf(
            'published: %d, unpublished: %d, rejected: %d, errors: %d',
            $report->count(ScheduledPublishingReport::OUTCOME_PUBLISHED),
            $report->count(ScheduledPublishingReport::OUTCOME_UNPUBLISHED),
            $report->count(ScheduledPublishingReport::OUTCOME_FAILED),
            $report->count(ScheduledPublishingReport::OUTCOME_ERROR),
        );

        if ($report->hasProblems()) {
            $io->warning($summary);

            return;
        }

        $io->success($summary);
    }

    /**
     * @return resource|null
     */
    private function acquireLock(): mixed
    {
        $directory = \dirname($this->lockFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        $handle = fopen($this->lockFile, 'c');
        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }
}
