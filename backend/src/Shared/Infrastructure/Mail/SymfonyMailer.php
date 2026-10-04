<?php

namespace App\Shared\Infrastructure\Mail;

use App\Shared\Application\Mail\CustomerMailServers;
use App\Shared\Application\Mail\Mailer;
use App\Shared\Application\Mail\MailNotSent;
use App\Shared\Application\Mail\MailServerCheck;
use App\Shared\Application\Mail\OutgoingEmail;
use App\Shared\Application\Mail\SmtpServer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;

/**
 * Sends synchronously, so a caller that must report a failure (retry: 502 RETRY_EMAIL_NOT_SENT) can. Callers that
 * do not care send from an event handler, which already runs on the worker.
 *
 * An email sent for an account that set up its own SMTP server (/profile › System) goes through that server, from
 * its sender; every other email goes through the platform's MAILER_DSN from SUPPORT_EMAIL.
 */
final class SymfonyMailer implements Mailer, MailServerCheck
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $supportEmail,
        private readonly CustomerMailServers $servers,
        private readonly SmtpTransports $transports,
        private readonly BodyRendererInterface $renderer,
    ) {
    }

    public function send(OutgoingEmail $email): void
    {
        $server = null === $email->customerId ? null : $this->servers->serverFor($email->customerId);
        if (null !== $server) {
            $this->sendThrough($server, $email);

            return;
        }

        try {
            $this->mailer->send($this->message($email, new Address($this->supportEmail, 'Mappi')));
        } catch (TransportExceptionInterface $e) {
            throw new MailNotSent($e->getMessage(), 0, $e);
        }
    }

    public function sendThrough(SmtpServer $server, OutgoingEmail $email): void
    {
        $message = $this->message($email, new Address($server->fromEmail, $server->fromName ?? ''));
        // Another transport than the framework's: the templated body is rendered here, not by its listener.
        $this->renderer->render($message);
        $transport = $this->transports->open($server);
        try {
            $transport->send($message);
        } catch (TransportExceptionInterface $e) {
            throw new MailNotSent($e->getMessage(), 0, $e);
        }
    }

    private function message(OutgoingEmail $email, Address $from): TemplatedEmail
    {
        $message = (new TemplatedEmail())
            ->from($from)
            ->to(...$email->to)
            ->subject($email->subject)
            ->htmlTemplate($email->template)
            ->locale($email->locale)
            ->context($email->context + ['locale' => $email->locale]);
        if ([] !== $email->bcc) {
            $message->bcc(...$email->bcc);
        }

        return $message;
    }
}
