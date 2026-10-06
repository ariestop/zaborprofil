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
        ] as $title => $paths) {
            if ($paths !== []) {
                $io->section(\sprintf('%s: %d', $title, \count($paths)));
                $io->listing($paths);
            }
        }

        $io->success(\sprintf(
            '%sImported: %d, previews created: %d, missing: %d, duplicates: %d.',
            $dryRun ? '[dry run] ' : '',
            \count($report->imported),
            \count($report->variantsCreated),
            \count($report->missingFiles),
            \count($report->duplicates),
        ));

        return Command::SUCCESS;
    }
}
