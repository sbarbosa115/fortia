<?php

namespace App\Shared\Infrastructure\Mail;

use App\Shared\Application\Mail\MailNotSent;
use App\Shared\Application\Mail\SmtpServer;
use Symfony\Component\Mailer\Transport\TransportInterface;

/** Opens the transport of an account's own SMTP server. Tests replace it with one that records instead of sending. */
interface SmtpTransports
{
    /** @throws MailNotSent when the server may not be used (e.g. a private address) */
    public function open(SmtpServer $server): TransportInterface;
}
