<?php

namespace <namespace>;

use App\Shared\Infrastructure\Persistence\PortableMigration;
use Doctrine\DBAL\Schema\Schema;

final class <className> extends PortableMigration
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
