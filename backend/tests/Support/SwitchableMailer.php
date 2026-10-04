<?php

namespace App\Tests\Support;

use App\Shared\Application\Mail\Mailer;
use App\Shared\Application\Mail\MailNotSent;
use App\Shared\Application\Mail\OutgoingEmail;

/**
 * The test container's Mailer: sends through the real one (the test transport records each message) unless a test
 * sets $failing, which makes the provider "refuse" every email, or lists addresses in $failingFor to refuse only
 * those. Reset by each test's kernel.
 */
final class SwitchableMailer implements Mailer
{
    public bool $failing = false;

    /** @var list<string> addresses whose emails the provider refuses */
    public array $failingFor = [];

    /** @var list<OutgoingEmail> what was sent, in order */
    public array $sent = [];

    public function __construct(private readonly Mailer $inner)
    {
    }

    /** Forgets what was sent so far. */
    public function clear(): void
    {
        $this->sent = [];
    }

    public function send(OutgoingEmail $email): void
    {
        if ($this->failing || [] !== array_intersect($email->to, $this->failingFor)) {
            throw new MailNotSent('The test made the email provider fail.');
        }
        $this->inner->send($email);
        $this->sent[] = $email;
    }
}
