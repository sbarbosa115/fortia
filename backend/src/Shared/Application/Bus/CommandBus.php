<?php

namespace App\Shared\Application\Bus;

/**
 * Writes go through here: one handler per command, one transaction per dispatch (the bus commits; handlers never
 * flush). Returns what the handler returned (an id or a small result, never an entity).
 */
interface CommandBus
{
    public function dispatch(object $command): mixed;
}
