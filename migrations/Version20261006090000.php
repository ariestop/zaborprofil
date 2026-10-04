<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable media_assets.alt and media_assets.title for Media Library metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_assets ADD alt VARCHAR(255) DEFAULT NULL, ADD title VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_assets DROP alt, DROP title');
    }
}
