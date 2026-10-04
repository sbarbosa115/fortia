<?php

namespace App\Identity\Infrastructure\Mail;

use App\Identity\Application\Port\AccountEmails;
use App\Shared\Application\Mail\Mailer;
use App\Shared\Application\Mail\MailServerCheck;
use App\Shared\Application\Mail\OutgoingEmail;
use App\Shared\Application\Mail\SmtpServer;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Identity emails as Twig templates (templates/emails/identity/), their texts in
 * translations/emails_identity+intl-icu.{es,en}.yaml.
 */
final class TemplatedAccountEmails implements AccountEmails
{
    private const DOMAIN = 'emails_identity';

    public function __construct(
        private readonly Mailer $mailer,
        private readonly MailServerCheck $check,
        private readonly TranslatorInterface $translator,
        private readonly string $adminFrontendUrl,
        private readonly string $supportEmail,
    ) {
    }

    public function welcome(string $email, string $name, string $accountLanguage): void
    {
        $locale = OutgoingEmail::localeOf($accountLanguage);
        $this->mailer->send(new OutgoingEmail(
            [$email],
            $this->translator->trans('welcome.subject', [], self::DOMAIN, $locale),
            'emails/identity/welcome.html.twig',
            ['name' => $name, 'console_url' => rtrim($this->adminFrontendUrl, '/').'/login', 'support_email' => $this->supportEmail],
            $locale,
            [$this->supportEmail],
        ));
    }

    public function recoveryCode(string $email, string $code, int $validMinutes, string $accountLanguage): void
    {
        $locale = OutgoingEmail::localeOf($accountLanguage);
        $this->mailer->send(new OutgoingEmail(
            [$email],
            $this->translator->trans('recovery.subject', [], self::DOMAIN, $locale),
            'emails/identity/password_recovery.html.twig',
            [
                'code' => $code,
                'minutes' => $validMinutes,
                'reset_url' => rtrim($this->adminFrontendUrl, '/').'/reset-password?code='.rawurlencode($code),
                'support_email' => $this->supportEmail,
            ],
            $locale,
        ));
    }

    public function smtpCheck(SmtpServer $server, string $email, string $accountLanguage, string $customerId): void
    {
        $locale = OutgoingEmail::localeOf($accountLanguage);
        $this->check->sendThrough($server, new OutgoingEmail(
            [$email],
            $this->translator->trans('smtp_check.subject', [], self::DOMAIN, $locale),
            'emails/identity/smtp_check.html.twig',
            ['host' => $server->host, 'port' => $server->port, 'from_email' => $server->fromEmail],
            $locale,
            [],
            $customerId,
        ));
    }
}
