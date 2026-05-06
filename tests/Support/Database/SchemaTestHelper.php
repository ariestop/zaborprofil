<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use LogicException;

/**
 * Builds the test database schema directly from Doctrine metadata, instead of
 * hand-maintained CREATE TABLE statements. Tests must run against the
 * PostgreSQL service from Docker Compose/CI so functional and integration
 * tests do not drift from the production database engine.
 */
final class SchemaTestHelper
{
    public static function recreateSchema(object $entityManager): void
    {
        self::guardTestDatabase($entityManager);

        /** @var list<object> $metadata */
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        if ([] === $metadata) {
            return;
        }

        self::dropAllTables($entityManager);

        self::createSchema($entityManager, $metadata);
        $entityManager->clear();
    }

    private static function guardTestDatabase(object $entityManager): void
    {
        $databaseName = (string) $entityManager->getConnection()->getDatabase();

        if (str_ends_with($databaseName, '_test')) {
            return;
        }

        throw new LogicException(sprintf(
            'SchemaTestHelper can only run on test databases (expected suffix "_test"), got "%s".',
            $databaseName,
        ));
    }

    private static function dropAllTables(object $entityManager): void
    {
        $connection = $entityManager->getConnection();
        $tables = $connection->createSchemaManager()->listTableNames();

        if ([] === $tables) {
            return;
        }

        $quotedTables = array_map(
            $connection->quoteIdentifier(...),
            $tables,
        );

        $connection->executeStatement('DROP TABLE IF EXISTS '.implode(', ', $quotedTables).' CASCADE');
    }

    /**
     * @param list<object> $metadata
     */
    private static function createSchema(object $entityManager, array $metadata): void
    {
        /** @var class-string $schemaToolClass */
        $schemaToolClass = 'Doctrine\\ORM\\Tools\\SchemaTool';

        if (!class_exists($schemaToolClass)) {
            throw new LogicException('Doctrine ORM SchemaTool is not available. Run composer install to restore dependencies.');
        }

        $tool = new $schemaToolClass($entityManager);

        if (!method_exists($tool, 'createSchema')) {
            throw new LogicException('Doctrine ORM SchemaTool does not support createSchema().');
        }

        $tool->createSchema($metadata);
    }
}
