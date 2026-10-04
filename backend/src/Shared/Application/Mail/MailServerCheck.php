<?php

namespace App\Shared\Application\Mail;

/** Sends one email through the given server, whatever is saved: the "Validate" button of /profile › System. */
interface MailServerCheck
{
    /** @throws MailNotSent when the server cannot be reached, refuses the login or refuses the message */
    public function sendThrough(SmtpServer $server, OutgoingEmail $email): void;
}
