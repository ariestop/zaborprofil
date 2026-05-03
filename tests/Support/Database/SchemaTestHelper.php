<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Builds the test database schema directly from Doctrine metadata, instead of
 * hand-maintained CREATE TABLE statements. Tests must run against the
 * PostgreSQL service from Docker Compose/CI so functional and integration
 * tests do not drift from the production database engine.
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
