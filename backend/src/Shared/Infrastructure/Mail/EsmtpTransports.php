<?php

namespace App\Shared\Infrastructure\Mail;

use App\Shared\Application\Mail\MailNotSent;
use App\Shared\Application\Mail\SmtpServer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Symfony's ESMTP transport for an account's server: "ssl" = implicit TLS, "tls" = STARTTLS required, "none" = no
 * TLS at all. A host that resolves to a private, loopback or reserved address is refused unless
 * SMTP_ALLOW_PRIVATE_HOSTS is on (dev, where Mailpit is an internal host), so a tenant cannot use the server check
 * to reach the platform's internal network.
 */
final class EsmtpTransports implements SmtpTransports
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        #[Autowire(env: 'bool:SMTP_ALLOW_PRIVATE_HOSTS')]
        private readonly bool $allowPrivateHosts,
    ) {
    }

    public function open(SmtpServer $server): TransportInterface
    {
        if (!$this->allowPrivateHosts) {
            $this->refusePrivateHosts($server->host);
        }
        $transport = new EsmtpTransport($server->host, $server->port, 'ssl' === $server->encryption);
        if ('tls' === $server->encryption) {
            $transport->setRequireTls(true);
        } elseif ('none' === $server->encryption) {
            $transport->setAutoTls(false);
        }
        if (null !== $server->username) {
            $transport->setUsername($server->username);
            $transport->setPassword($server->password ?? '');
        }
        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setTimeout(self::TIMEOUT_SECONDS);
        }

        return $transport;
    }

    private function refusePrivateHosts(string $host): void
    {
        $addresses = filter_var($host, \FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ([] === $addresses) {
            throw new MailNotSent('connection: the server name does not resolve.');
        }
        foreach ($addresses as $address) {
            if (false === filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
                throw new MailNotSent('blocked: the server resolves to a private or reserved address.');
            }
        }
    }
}
