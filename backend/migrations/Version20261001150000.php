<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Billing's own tables (item 2): the payments recorded from invoice.paid, the payment webhook events already applied
 * (idempotency), and the fake payment gateway's state and outbox (dev and tests).
 */
final class Version20261001150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Billing: payments, processed payment webhook events, and the fake payment gateway state (item billing)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE billing_payment (id INT AUTO_INCREMENT NOT NULL, gateway_event_id VARCHAR(255) NOT NULL, customer_id VARCHAR(16) NOT NULL, invoice_id VARCHAR(255) DEFAULT NULL, subscription_id VARCHAR(255) DEFAULT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, billing_reason VARCHAR(50) DEFAULT NULL, paid_at DATETIME NOT NULL, INDEX idx_billing_payment_customer (customer_id, paid_at), UNIQUE INDEX uniq_billing_payment_event (gateway_event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE billing_processed_webhook_event (event_id VARCHAR(255) NOT NULL, type VARCHAR(100) NOT NULL, processed_at DATETIME NOT NULL, PRIMARY KEY (event_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE fake_gateway_event (seq INT AUTO_INCREMENT NOT NULL, delivered_at DATETIME DEFAULT NULL, response_status INT DEFAULT NULL, event_id VARCHAR(64) NOT NULL, type VARCHAR(100) NOT NULL, payload LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX idx_fake_gateway_event_pending (delivered_at), UNIQUE INDEX uniq_fake_gateway_event_id (event_id), PRIMARY KEY (seq)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE fake_gateway_object (id VARCHAR(64) NOT NULL, kind VARCHAR(32) NOT NULL, data JSON NOT NULL, created_at DATETIME NOT NULL, INDEX idx_fake_gateway_object_kind (kind, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE billing_payment');
        $this->addSql('DROP TABLE billing_processed_webhook_event');
        $this->addSql('DROP TABLE fake_gateway_event');
        $this->addSql('DROP TABLE fake_gateway_object');
    }
}
