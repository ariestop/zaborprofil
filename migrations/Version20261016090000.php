<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Admin users: display name; seolool@yandex.ru becomes super administrator (by owner request, no-op if the account does not exist).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_users ADD name VARCHAR(120) DEFAULT NULL');
        $this->addSql("UPDATE admin_users SET roles = JSON_ARRAY('ROLE_SUPER_ADMIN'), updated_at = NOW() WHERE email = 'seolool@yandex.ru' AND NOT JSON_CONTAINS(roles, '\"ROLE_SUPER_ADMIN\"')");
    }

    public function down(Schema $schema): void
    {
        // Роль не откатывается: прежний набор ролей не хранится, а снять права можно в разделе «Пользователи».
        $this->addSql('ALTER TABLE admin_users DROP name');
    }
}
