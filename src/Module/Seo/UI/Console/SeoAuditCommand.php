<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Console;

use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Seo\Application\Audit\SeoAuditEngine;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seo:audit', description: 'Run SEO audit for one page or all admin pages.')]
final class SeoAuditCommand extends Command
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly SeoAuditEngine $audit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('page-id', null, InputOption::VALUE_REQUIRED, 'Audit a single page by ULID.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print machine-readable JSON.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reports = $this->reports($input->getOption('page-id'));

        if ($input->getOption('json') === true) {
            $output->writeln(json_encode(array_map(static fn ($report): array => $report->toArray(), $reports), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $this->hasBlockingReports($reports) ? Command::FAILURE : Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        $rows = [];
        foreach ($reports as $report) {
            if ($report->issues === []) {
                $rows[] = [$report->path, 'OK', '-', '-'];
                continue;
            }

            foreach ($report->issues as $issue) {
                $rows[] = [$report->path, $issue->severity->value, $issue->code, $issue->message];
            }
        }

        $io->table(['Path', 'Severity', 'Code', 'Message'], $rows);

        if ($this->hasBlockingReports($reports)) {
            $io->error('SEO audit found blocking P0/P1 issues.');

            return Command::FAILURE;
        }

        $io->success('SEO audit passed without blocking issues.');

        return Command::SUCCESS;
    }

    /**
     * @return list<\App\Module\Seo\Application\Audit\SeoAuditReport>
     */
    private function reports(mixed $pageId): array
    {
        if (\is_string($pageId) && $pageId !== '') {
            return [$this->audit->auditPage($this->pages->get($pageId))];
        }

        return array_map($this->audit->auditPage(...), $this->pages->findAllForAdmin());
    }

    /**
     * @param list<\App\Module\Seo\Application\Audit\SeoAuditReport> $reports
     */
    private function hasBlockingReports(array $reports): bool
    {
        return array_any($reports, fn ($report) => $report->hasBlockingIssues());
    }
}
