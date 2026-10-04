<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add seo_not_found_log: aggregated public 404 journal for the SEO panel.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE seo_not_found_log (id BINARY(16) NOT NULL, path VARCHAR(512) NOT NULL, path_hash VARCHAR(40) NOT NULL, hit_count INT NOT NULL, first_seen_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, referrer VARCHAR(512) DEFAULT NULL, INDEX idx_seo_not_found_last_seen (last_seen_at), INDEX idx_seo_not_found_hits (hit_count), UNIQUE INDEX uniq_seo_not_found_path_hash (path_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE seo_not_found_log');
    }
}
