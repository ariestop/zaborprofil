<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add media asset variants metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE media_assets ADD variants JSONB NOT NULL DEFAULT '[]'::jsonb");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_assets DROP variants');
    }
}
