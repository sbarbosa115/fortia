<?php

declare(strict_types=1);

namespace App\Shared\Application\Mail;

/**
 * Transactional email (PRD §7.21, §13.6): sent from SUPPORT_EMAIL with an HTML template in es or en.
 * Templates live in templates/emails/<context>/<name>.html.twig and extend templates/emails/layout.html.twig.
 */
interface Mailer
{
    /** @throws MailNotSent when the provider refuses or cannot be reached */
    public function send(OutgoingEmail $email): void;
}
