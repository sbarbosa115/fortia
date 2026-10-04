<?php

namespace App\Identity\Application\Command;

use App\Identity\Application\Port\AccountEmails;
use App\Identity\Domain\Error\InvalidSmtpServer;
use App\Identity\Domain\Error\SmtpCheckFailed;
use App\Identity\Domain\Repository\CustomerRepository;
use App\Identity\Domain\Repository\SystemSettingsRepository;
use App\Shared\Application\Mail\MailNotSent;
use App\Shared\Application\Mail\SmtpServer;
use App\Shared\Application\Security\SecretBox;
use App\Shared\Application\Security\SecretNotReadable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CheckSmtpServerHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly SystemSettingsRepository $settings,
        private readonly SecretBox $box,
        private readonly AccountEmails $emails,
    ) {
    }

    public function __invoke(CheckSmtpServer $command): void
    {
        $customer = $this->customers->get($command->customerId);
        $smtp = SmtpFields::merge($this->settings->find($command->customerId)?->smtp(), $command->fields, $this->box)
            ?? throw new InvalidSmtpServer('smtp_host', 'Enter the server name to check it.');
        try {
            $password = null === $smtp->sealedPassword ? null : $this->box->open($smtp->sealedPassword);
        } catch (SecretNotReadable) {
            throw new InvalidSmtpServer('smtp_password', 'The saved password cannot be read: enter it again.');
        }

        $server = new SmtpServer($smtp->host, $smtp->port, $smtp->encryption, $smtp->username, $password, $smtp->fromEmail, $smtp->fromName);
        try {
            $this->emails->smtpCheck($server, $command->to, $customer->language(), $command->customerId);
        } catch (MailNotSent $e) {
            throw new SmtpCheckFailed(SmtpCheckFailed::reasonOf($e->getMessage()), $e);
        }
    }
}
