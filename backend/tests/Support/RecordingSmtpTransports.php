<?php

namespace App\Tests\Support;

use App\Shared\Application\Mail\SmtpServer;
use App\Shared\Infrastructure\Mail\SmtpTransports;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * The test container's account SMTP servers: nothing is sent; each message is recorded with the server it would have
 * gone through. A test sets $failWith to make the server refuse ("Failed to authenticate…", "Connection refused"…).
 */
final class RecordingSmtpTransports implements SmtpTransports
{
    public ?string $failWith = null;

    /** @var list<array{server: SmtpServer, email: Email}> */
    public array $sent = [];

    public function open(SmtpServer $server): TransportInterface
    {
        $recorder = $this;

        return new class($server, $recorder) extends AbstractTransport {
            public function __construct(private readonly SmtpServer $server, private readonly RecordingSmtpTransports $recorder)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                if (null !== $this->recorder->failWith) {
                    throw new TransportException($this->recorder->failWith);
                }
                $email = $message->getOriginalMessage();
                \assert($email instanceof Email);
                $this->recorder->sent[] = ['server' => $this->server, 'email' => $email];
            }

            public function __toString(): string
            {
                return 'recording://'.$this->server->host;
            }
        };
    }
}
