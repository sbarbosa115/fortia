<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An account's own usage/analytics service (/profile › System, PRD §13.8): its base URL and its API key, sealed by
 * SecretBox. Without them the platform's ANALYTICS_BASE_URL and ANALYTICS_API_KEY are used.
 */
final class Version20261004210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'customer_system_settings: analytics_base_url, analytics_api_key, analytics_api_key_last4';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_system_settings ADD analytics_base_url VARCHAR(255) DEFAULT NULL, ADD analytics_api_key LONGTEXT DEFAULT NULL, ADD analytics_api_key_last4 VARCHAR(4) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_system_settings DROP analytics_base_url, DROP analytics_api_key, DROP analytics_api_key_last4');
    }
}
