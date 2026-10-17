<?php

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\CollatedMigration;
use Doctrine\DBAL\Schema\Schema;

/** Integrations (D19): the webhook delivery log keeps what went wrong on the last failed attempt. */
final class Version20261001041200 extends CollatedMigration
{
    public function getDescription(): string
    {
        return 'webhook_delivery.last_error';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webhook_delivery ADD last_error VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webhook_delivery DROP last_error');
    }
}
