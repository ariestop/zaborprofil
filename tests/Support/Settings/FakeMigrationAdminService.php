<?php

declare(strict_types=1);

namespace App\Tests\Support\Settings;

use App\Module\Settings\Application\Service\MigrationAdminServiceInterface;
use Throwable;

/**
 * Подменяет MigrationAdminService в APP_ENV=test: тесты не должны менять схему тестовой БД.
 * Состояние статическое, потому что KernelBrowser пересобирает контейнер между запросами.
 */
final class FakeMigrationAdminService implements MigrationAdminServiceInterface
{
    public static ?Throwable $failure = null;

    /**
     * @var list<string>
     */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$failure = null;
        self::$calls = [];
    }

    public function list(): array
    {
        return [
            [
                'version' => 'DoctrineMigrations\\Version1',
                'file' => 'Version1.php',
                'description' => 'Applied migration',
                'isApplied' => true,
                'executedAt' => null,
                'executionTime' => null,
                'canApply' => false,
                'canRollback' => true,
            ],
            [
                'version' => 'DoctrineMigrations\\Version2',
                'file' => 'Version2.php',
                'description' => 'Pending migration',
                'isApplied' => false,
                'executedAt' => null,
                'executionTime' => null,
                'canApply' => true,
                'canRollback' => false,
            ],
        ];
    }

    public function apply(string $version): array
    {
        self::$calls[] = 'apply:'.$version;
        if (self::$failure !== null) {
            throw self::$failure;
        }

        return ['status' => 'applied', 'executedCount' => 1];
    }

    public function rollback(string $version): array
    {
        self::$calls[] = 'rollback:'.$version;
        if (self::$failure !== null) {
            throw self::$failure;
        }

        return ['status' => 'rolled_back', 'executedCount' => 1];
    }
}
