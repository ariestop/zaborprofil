<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;

/**
 * Builds the test database schema directly from Doctrine metadata, instead of
 * hand-maintained CREATE TABLE statements. Tests must run against the
 * MySQL service from Docker Compose/CI so functional and integration
 * tests do not drift from the production database engine.
 */
final class SchemaTestHelper
{
    public static function recreateSchema(EntityManagerInterface $entityManager): void
    {
        self::guardTestDatabase($entityManager);

        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        if ([] === $metadata) {
            return;
        }

        self::dropAllTables($entityManager);

        self::createSchema($entityManager, $metadata);
        $entityManager->clear();
    }

    private static function guardTestDatabase(EntityManagerInterface $entityManager): void
    {
        $databaseName = (string) $entityManager->getConnection()->getDatabase();

        if (str_ends_with($databaseName, '_test')) {
            return;
        }

        throw new LogicException(\sprintf(
            'SchemaTestHelper can only run on test databases (expected suffix "_test"), got "%s".',
            $databaseName,
        ));
    }

    private static function dropAllTables(EntityManagerInterface $entityManager): void
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
     * @param list<ClassMetadata<object>> $metadata
     */
    private static function createSchema(EntityManagerInterface $entityManager, array $metadata): void
    {
        $tool = new SchemaTool($entityManager);

        $tool->createSchema($metadata);
    }
}
