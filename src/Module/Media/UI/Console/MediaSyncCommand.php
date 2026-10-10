<?php

declare(strict_types=1);

namespace App\Module\Media\UI\Console;

use App\Module\Media\Application\Service\MediaLibrarySynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:media:sync',
    description: 'Register images referenced by content in the media library and create missing responsive previews.',
)]
final class MediaSyncCommand extends Command
{
    public function __construct(private readonly MediaLibrarySynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would change.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $report = $this->synchronizer->sync($dryRun);

        foreach ([
            'Imported into media library' => $report->imported,
            'Responsive previews created' => $report->variantsCreated,
            'Referenced but missing on disk' => $report->missingFiles,
            'Same file already in library under another path' => $report->duplicates,
            'Skipped (not a supported image)' => $report->skipped,
            'Skipped (exceeds upload limits)' => $report->tooLarge,
        ] as $title => $paths) {
            if ($paths !== []) {
                $io->section(\sprintf('%s: %d', $title, \count($paths)));
                $io->listing($paths);
            }
        }

        $summary = \sprintf(
            '%sImported: %d, previews created: %d, missing: %d, duplicates: %d, too large: %d, failed: %d.',
            $dryRun ? '[dry run] ' : '',
            \count($report->imported),
            \count($report->variantsCreated),
            \count($report->missingFiles),
            \count($report->duplicates),
            \count($report->tooLarge),
            \count($report->failed),
        );

        if ($report->failed !== []) {
            $io->section(\sprintf('Failed: %d', \count($report->failed)));
            $io->listing(array_map(
                static fn (string $path, string $reason): string => $path.' — '.$reason,
                array_keys($report->failed),
                array_values($report->failed),
            ));
            // Остальные файлы обработаны; код ошибки нужен, чтобы деплой показал предупреждение.
            $io->error($summary);

            return Command::FAILURE;
        }

        $io->success($summary);

        return Command::SUCCESS;
    }
}
