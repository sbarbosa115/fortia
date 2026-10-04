<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A questionnaire's tags: a JSON list of free-text labels ("AP-03", "NP-12"…). Existing questionnaires get none.
 */
final class Version20261004161226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'questionnaire.tags (JSON list, empty for existing rows)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE questionnaire ADD tags JSON DEFAULT NULL');
        $this->addSql('UPDATE questionnaire SET tags = JSON_ARRAY()');
        $this->addSql('ALTER TABLE questionnaire MODIFY tags JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE questionnaire DROP tags');
    }
}
