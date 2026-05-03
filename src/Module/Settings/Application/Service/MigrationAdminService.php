<?php

declare(strict_types=1);

namespace App\Module\Settings\Application\Service;

use DateTimeInterface;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\MigrationClassNotFound;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\Version;
use InvalidArgumentException;
use ReflectionClass;

final readonly class MigrationAdminService
{
    public function __construct(
        private DependencyFactory $dependencyFactory,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $availableMigrations = $this->availableMigrations();
        $executedMigrations = $this->dependencyFactory->getMetadataStorage()->getExecutedMigrations();
        $latestExecutedVersion = $this->latestExecutedVersion($availableMigrations);

        $items = [];
        foreach ($availableMigrations as $migration) {
            $version = $migration->getVersion();
            $executedMigration = $this->executedMigration((string) $version);
            $isApplied = $executedMigrations->hasMigration($version);

            $items[] = [
                'version' => (string) $version,
                'file' => $this->fileName($migration),
                'description' => $this->description($migration),
                'isApplied' => $isApplied,
                'executedAt' => $executedMigration?->getExecutedAt()?->format(DateTimeInterface::ATOM),
                'executionTime' => $executedMigration?->getExecutionTime(),
                'canApply' => !$isApplied,
                'canRollback' => $isApplied && (string) $version === $latestExecutedVersion,
            ];
        }

        return $items;
    }

    /**
     * @return array{status: string, executedCount: int}
     */
    public function apply(string $version): array
    {
        $this->assertKnownVersion($version);
        $this->dependencyFactory->getMetadataStorage()->ensureInitialized();

        $plan = $this->dependencyFactory->getMigrationPlanCalculator()->getPlanUntilVersion(new Version($version));
        if ($plan->getDirection() !== Direction::UP) {
            throw new InvalidArgumentException('Migration is already applied.');
        }

        $this->dependencyFactory->getMigrator()->migrate($plan, new MigratorConfiguration());

        return [
            'status' => 'applied',
            'executedCount' => \count($plan),
        ];
    }

    /**
     * @return array{status: string, executedCount: int}
     */
    public function rollback(string $version): array
    {
        $availableMigrations = $this->availableMigrations();
        $this->assertKnownVersion($version);
        $this->dependencyFactory->getMetadataStorage()->ensureInitialized();

        $latestExecutedVersion = $this->latestExecutedVersion($availableMigrations);
        if ($latestExecutedVersion !== $version) {
            throw new InvalidArgumentException('Only the latest applied migration can be rolled back.');
        }

        $targetVersion = $this->previousVersion($availableMigrations, $version);
        $plan = $this->dependencyFactory->getMigrationPlanCalculator()->getPlanUntilVersion(new Version($targetVersion));
        if ($plan->getDirection() !== Direction::DOWN) {
            throw new InvalidArgumentException('Migration cannot be rolled back.');
        }

        $this->dependencyFactory->getMigrator()->migrate($plan, new MigratorConfiguration());

        return [
            'status' => 'rolled_back',
            'executedCount' => \count($plan),
        ];
    }

    /**
     * @return list<AvailableMigration>
     */
    private function availableMigrations(): array
    {
        $migrations = $this->dependencyFactory->getMigrationRepository()->getMigrations()->getItems();
        $comparator = $this->dependencyFactory->getVersionComparator();
        usort(
            $migrations,
            static fn (AvailableMigration $left, AvailableMigration $right): int => $comparator->compare($left->getVersion(), $right->getVersion()),
        );

        return $migrations;
    }

    private function assertKnownVersion(string $version): void
    {
        if (!$this->dependencyFactory->getMigrationRepository()->hasMigration($version)) {
            throw MigrationClassNotFound::new($version);
        }
    }

    /**
     * @param list<AvailableMigration> $availableMigrations
     */
    private function latestExecutedVersion(array $availableMigrations): ?string
    {
        $executedMigrations = $this->dependencyFactory->getMetadataStorage()->getExecutedMigrations();
        $latestVersion = null;

        foreach ($availableMigrations as $migration) {
            if ($executedMigrations->hasMigration($migration->getVersion())) {
                $latestVersion = (string) $migration->getVersion();
            }
        }

        return $latestVersion;
    }

    /**
     * @param list<AvailableMigration> $availableMigrations
     */
    private function previousVersion(array $availableMigrations, string $version): string
    {
        $previousVersion = '0';
        foreach ($availableMigrations as $migration) {
            $currentVersion = (string) $migration->getVersion();
            if ($currentVersion === $version) {
                return $previousVersion;
            }

            $previousVersion = $currentVersion;
        }

        throw MigrationClassNotFound::new($version);
    }

    private function executedMigration(string $version): ?ExecutedMigration
    {
        foreach ($this->dependencyFactory->getMetadataStorage()->getExecutedMigrations()->getItems() as $migration) {
            if ((string) $migration->getVersion() === $version) {
                return $migration;
            }
        }

        return null;
    }

    private function fileName(AvailableMigration $migration): string
    {
        $fileName = (new ReflectionClass($migration->getMigration()))->getFileName();

        return \is_string($fileName) ? basename($fileName) : basename(str_replace('\\', '/', (string) $migration->getVersion())).'.php';
    }

    private function description(AvailableMigration $migration): string
    {
        $description = trim($migration->getMigration()->getDescription());

        return $description !== '' ? $description : 'Описание не задано.';
    }
}
