<?php

namespace App\Identity\Application\Port;

use App\Shared\Application\Mail\MailNotSent;

/** The emails of the Identity context (PRD §7.21), in the account's language (es-CO / en-US). */
interface AccountEmails
{
    /**
     * D20: sent on sign-up, with a BCC to support.
     *
     * @throws MailNotSent
     */
    public function welcome(string $email, string $name, string $accountLanguage): void;

    /**
     * The password recovery code and a link to {ADMIN_URL}/reset-password?code=… (PRD §7.21).
     *
     * @throws MailNotSent
     */
    public function recoveryCode(string $email, string $code, int $validMinutes, string $accountLanguage): void;
}
