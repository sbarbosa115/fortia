<?php

namespace App\Shared\Application\Mail;

/** The SMTP server an account set up in /profile › System, or null: its emails go through the platform's MAILER_DSN. */
interface CustomerMailServers
{
    public function serverFor(string $customerId): ?SmtpServer;
}
