<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant ROLE_SUPER_ADMIN to existing admin users before stricter RBAC is enabled.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE admin_users
            SET roles = (
                SELECT jsonb_agg(DISTINCT role)
                FROM jsonb_array_elements_text(roles || '["ROLE_SUPER_ADMIN"]'::jsonb) AS role
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE admin_users
            SET roles = COALESCE(
                (
                    SELECT jsonb_agg(role)
                    FROM jsonb_array_elements_text(roles) AS role
                    WHERE role <> 'ROLE_SUPER_ADMIN'
                ),
                '[]'::jsonb
            )
        SQL);
    }
}
