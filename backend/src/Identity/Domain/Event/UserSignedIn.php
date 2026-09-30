<?php

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12. Payload: {email}. */
final class UserSignedIn extends BaseDomainEvent
{
}
