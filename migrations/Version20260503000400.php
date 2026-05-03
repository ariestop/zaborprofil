<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lead anti-spam metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leads ADD spam_score INT NOT NULL DEFAULT 0');
        $this->addSql("ALTER TABLE leads ADD spam_reasons JSONB NOT NULL DEFAULT '[]'::jsonb");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leads DROP spam_score');
        $this->addSql('ALTER TABLE leads DROP spam_reasons');
    }
}
