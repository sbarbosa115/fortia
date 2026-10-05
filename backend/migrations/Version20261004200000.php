<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Whether a project's follow-ups go to review once complete (requires_review) or are simply completed. Existing
 * projects and assignations keep the review.
 */
final class Version20261004200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'project.requires_review and assignation.requires_review (true for existing rows)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD requires_review TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE assignation ADD requires_review TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP requires_review');
        $this->addSql('ALTER TABLE assignation DROP requires_review');
    }
}
