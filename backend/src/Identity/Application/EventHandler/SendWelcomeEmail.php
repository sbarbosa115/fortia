<?php

namespace App\Identity\Application\EventHandler;

use App\Identity\Application\Port\AccountEmails;
use App\Identity\Domain\Event\UserRootRegistered;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** D20: the welcome email (es/en, BCC to support) is sent on every new account. */
#[AsMessageHandler(bus: 'event.bus')]
final class SendWelcomeEmail
{
    public function __construct(private readonly AccountEmails $emails)
    {
    }

    public function __invoke(UserRootRegistered $event): void
    {
        $payload = $event->payload();
        $this->emails->welcome((string) ($payload['email'] ?? ''), (string) ($payload['name'] ?? ''), (string) ($payload['language'] ?? 'es-CO'));
    }
}
