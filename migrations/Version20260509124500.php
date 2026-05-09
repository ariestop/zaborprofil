<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version20260509124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete legacy page blocks after structured builder migration.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $this->addSql("
            DELETE FROM content_page_blocks
            WHERE type IN (
                'hero',
                'text',
                'text_image',
                'price_cards',
                'feature_grid',
                'cta_form',
                'telegram_cta',
                'contacts',
                'map',
                'portfolio_grid',
                'seo_text',
                'html_embed',
                'table',
                'before_after',
                'calculator_placeholder',
                'review_cards',
                'documents'
            )
        ");
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        throw new IrreversibleMigration('Cannot restore deleted legacy blocks automatically.');
    }
}
