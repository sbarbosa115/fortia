<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mail;

use App\Shared\Application\Mail\Mailer;
use App\Shared\Application\Mail\MailNotSent;
use App\Shared\Application\Mail\OutgoingEmail;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends synchronously, so a caller that must report a failure (retry: 502 RETRY_EMAIL_NOT_SENT) can. Callers that
 * do not care send from an event handler, which already runs on the worker.
 */
final class SymfonyMailer implements Mailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $supportEmail,
    ) {
    }

    public function send(OutgoingEmail $email): void
    {
        $message = (new TemplatedEmail())
            ->from(new Address($this->supportEmail, 'Mappi'))
            ->to(...$email->to)
            ->subject($email->subject)
            ->htmlTemplate($email->template)
            ->locale($email->locale)
            ->context($email->context + ['locale' => $email->locale]);
        if ([] !== $email->bcc) {
            $message->bcc(...$email->bcc);
        }

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            throw new MailNotSent($e->getMessage(), 0, $e);
        }
    }
}
