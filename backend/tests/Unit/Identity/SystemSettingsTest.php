<?php

namespace App\Tests\Unit\Identity;

use App\Identity\Domain\Error\InvalidSmtpServer;
use App\Identity\Domain\Model\SmtpConfiguration;
use App\Identity\Domain\Model\SystemSettings;
use PHPUnit\Framework\TestCase;

final class SystemSettingsTest extends TestCase
{
    /** Stands for a SecretBox-sealed value; a made-up fixture. */
    private const SEALED = 'sealed-fixture';

    private function smtp(string $host = 'smtp.acme.test', int $port = 587, string $encryption = 'tls', ?string $username = 'mailer', ?string $password = self::SEALED, string $fromEmail = 'hello@acme.test', ?string $fromName = 'Acme'): SmtpConfiguration
    {
        return new SmtpConfiguration($host, $port, $encryption, $username, $password, $fromEmail, $fromName);
    }

    public function testANewAccountHasNoSmtpServerAndNoKeySoThePlatformDefaultsApply(): void
    {
        $settings = new SystemSettings('ACME0001', new \DateTimeImmutable());

        self::assertNull($settings->smtp(), 'no SMTP server of its own: emails go through the platform server');
        self::assertNull($settings->sealedOpenAiKey(), 'no key of its own: the platform OpenAI key is used');
    }

    public function testTheSmtpServerIsSavedAndCanBeRemoved(): void
    {
        $settings = new SystemSettings('ACME0001', new \DateTimeImmutable());

        $settings->changeSmtp($this->smtp(), new \DateTimeImmutable());
        self::assertSame('smtp.acme.test', $settings->smtp()?->host);

        $settings->changeSmtp(null, new \DateTimeImmutable());
        self::assertNull($settings->smtp(), 'removing the server goes back to the platform server');
    }

    public function testTheOpenAiKeyKeepsOnlyItsLastFourCharactersVisible(): void
    {
        $settings = new SystemSettings('ACME0001', new \DateTimeImmutable());

        $settings->changeOpenAiKey('sealed-value', 'sk-proj-abcd1234', new \DateTimeImmutable());

        self::assertSame('sealed-value', $settings->sealedOpenAiKey());
        self::assertSame('1234', $settings->openAiKeyHint(), 'the console only ever shows the last 4 characters');

        $settings->changeOpenAiKey(null, null, new \DateTimeImmutable());
        self::assertNull($settings->sealedOpenAiKey());
        self::assertNull($settings->openAiKeyHint());
    }

    /** @return iterable<string, array{0: array<string, mixed>}> */
    public static function badServers(): iterable
    {
        yield 'a host with a scheme' => [['host' => 'smtp://smtp.acme.test']];
        yield 'a host with spaces' => [['host' => 'smtp acme']];
        yield 'an empty host' => [['host' => '']];
        yield 'port 0' => [['port' => 0]];
        yield 'port 65536' => [['port' => 65536]];
        yield 'an unknown encryption' => [['encryption' => 'starttls']];
        yield 'a sender that is not an email' => [['fromEmail' => 'acme']];
        yield 'a password without a username' => [['username' => null]];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @dataProvider badServers
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('badServers')]
    public function testAnInvalidServerIsRefused(array $override): void
    {
        $this->expectException(InvalidSmtpServer::class);
        $this->smtp(...$override);
    }

    public function testAServerWithoutAuthenticationIsAllowed(): void
    {
        $smtp = $this->smtp(username: null, password: null, encryption: 'none', port: 25);

        self::assertFalse($smtp->authenticates(), 'an open relay inside the customer\'s network needs no login');
    }
}
