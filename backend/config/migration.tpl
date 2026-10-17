<?php

namespace <namespace>;

use App\Shared\Infrastructure\Persistence\CollatedMigration;
use Doctrine\DBAL\Schema\Schema;

final class <className> extends CollatedMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
<up>
    }

    public function down(Schema $schema): void
    {
<down>
    }<override>
}
