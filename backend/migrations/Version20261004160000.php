<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An account's system settings (/profile › System): its own SMTP server and OpenAI key, the secrets sealed by
 * SecretBox. One row per account at most, gone with the account.
 */
final class Version20261004160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create customer_system_settings (SMTP server, OpenAI key)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_system_settings (customer_id VARCHAR(16) NOT NULL, smtp_host VARCHAR(255) DEFAULT NULL, smtp_port INT DEFAULT NULL, smtp_encryption VARCHAR(8) DEFAULT NULL, smtp_username VARCHAR(255) DEFAULT NULL, smtp_password LONGTEXT DEFAULT NULL, smtp_from_email VARCHAR(254) DEFAULT NULL, smtp_from_name VARCHAR(100) DEFAULT NULL, openai_api_key LONGTEXT DEFAULT NULL, openai_api_key_last4 VARCHAR(4) DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (customer_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE customer_system_settings ADD CONSTRAINT fk_customer_system_settings_customer FOREIGN KEY (customer_id) REFERENCES customer (customer_id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_system_settings');
    }
}
