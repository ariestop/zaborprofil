<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Builds the test database schema directly from Doctrine metadata, instead of
 * hand-maintained CREATE TABLE statements. The same helper works against
 * SQLite (default `.env.test`) and PostgreSQL (CI), so functional and
 * integration tests no longer drift from the production migrations.
 */
final class SchemaTestHelper
{
    public static function recreateSchema(EntityManagerInterface $entityManager): void
    {
        /** @var list<ClassMetadata<object>> $metadata */
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        if ([] === $metadata) {
            return;
        }

        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }
}
