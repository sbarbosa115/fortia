<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Publishes domain events. Called inside a command handler, they are dispatched only after that command's
 * transaction commits; called elsewhere (a read endpoint), right away.
 */
interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
