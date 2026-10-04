<?php

namespace App\Tests\Functional\Api\Identity;

use App\Identity\Domain\Model\SystemSettings;
use App\Shared\Application\Llm\OpenAiKeys;
use App\Shared\Application\Mail\CustomerMailServers;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\RecordingSmtpTransports;
use Symfony\Component\Mime\Address;

/**
 * /customer/{customer_id}/system-settings: the System tab of /profile. An account's own SMTP server (with a server
 * check that sends a test email) and its own OpenAI API key, both secrets stored encrypted and never returned.
 */
final class SystemSettingsTest extends ApiTestCase
{
    /** A made-up fixture, not a credential. */
    private const FIXTURE_SMTP_PASS = 'fixture-value-for-tests';

    private const SERVER = [
        'smtp_host' => 'smtp.acme.test',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => 'mailer@acme.test',
        'smtp_password' => self::FIXTURE_SMTP_PASS,
        'smtp_from_email' => 'hello@acme.test',
        'smtp_from_name' => 'Acme',
    ];

    private function transports(): RecordingSmtpTransports
    {
        return static::getContainer()->get(RecordingSmtpTransports::class);
    }

    public function testANewAccountUsesThePlatformServerAndKey(): void
    {
        $owner = $this->account('ACME0001');

        $settings = $this->data($this->api('GET', '/api/v1/customer/ACME0001/system-settings', as: $owner));

        self::assertSame([
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_encryption' => null,
            'smtp_username' => null,
            'smtp_password_set' => false,
            'smtp_from_email' => null,
            'smtp_from_name' => null,
            'openai_api_key_set' => false,
            'openai_api_key_last4' => null,
        ], $settings, 'nothing of its own: emails and AI use the platform defaults');
    }

    public function testTheSmtpServerIsSavedWithoutEverReturningThePassword(): void
    {
        $owner = $this->account('ACME0001');

        $saved = $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', self::SERVER, as: $owner));

        self::assertSame('smtp.acme.test', $saved['smtp_host']);
        self::assertSame(587, $saved['smtp_port']);
        self::assertTrue($saved['smtp_password_set'], 'the console knows a password is saved');
        self::assertStringNotContainsString(self::FIXTURE_SMTP_PASS, json_encode($saved) ?: '', 'the password is never returned');
        $row = $this->em()->getConnection()->fetchAssociative('SELECT smtp_password FROM customer_system_settings WHERE customer_id = ?', ['ACME0001']);
        self::assertIsArray($row);
        self::assertStringNotContainsString(self::FIXTURE_SMTP_PASS, (string) $row['smtp_password'], 'the password is stored encrypted');
    }

    public function testAFieldNotSentKeepsItsValueAndAnEmptyHostRemovesTheServer(): void
    {
        $owner = $this->account('ACME0001');
        $this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', self::SERVER, as: $owner);

        $changed = $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['smtp_port' => 2525], as: $owner));
        self::assertSame(2525, $changed['smtp_port']);
        self::assertTrue($changed['smtp_password_set'], 'a password not sent is kept');
        self::assertSame('hello@acme.test', $changed['smtp_from_email']);

        $removed = $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['smtp_host' => ''], as: $owner));
        self::assertNull($removed['smtp_host'], 'an empty host goes back to the platform server');
        self::assertFalse($removed['smtp_password_set']);
    }

    public function testAnIncompleteOrMalformedServerIsRefused(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['smtp_host' => 'smtp.acme.test'], as: $owner), 422, 'INVALID_SMTP_SERVER', 'a host alone has no port, encryption or sender');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['smtp_host' => 'smtp://x'] + self::SERVER, as: $owner), 422, 'INVALID_SMTP_SERVER', 'the host is a name, not a URL');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['smtp_encryption' => 'starttls'] + self::SERVER, as: $owner), 400, 'VALIDATION_ERROR', 'encryption is tls, ssl or none');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['smtp_port' => '587'] + self::SERVER, as: $owner), 400, 'VALIDATION_ERROR', 'the port is an integer');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['unknown' => 1], as: $owner), 400, 'VALIDATION_ERROR', 'no extra fields');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', [], as: $owner), 400, 'VALIDATION_ERROR', 'at least one field');
    }

    public function testTheOpenAiKeyIsStoredEncryptedAndOnlyItsLastFourCharactersAreShown(): void
    {
        $owner = $this->account('ACME0001');

        $saved = $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['openai_api_key' => '  sk-proj-abcdef9876  '], as: $owner));

        self::assertTrue($saved['openai_api_key_set']);
        self::assertSame('9876', $saved['openai_api_key_last4']);
        self::assertStringNotContainsString('sk-proj-abcdef9876', json_encode($saved) ?: '', 'the key is never returned');
        $row = $this->em()->getConnection()->fetchAssociative('SELECT openai_api_key FROM customer_system_settings WHERE customer_id = ?', ['ACME0001']);
        self::assertIsArray($row);
        self::assertStringNotContainsString('sk-proj-abcdef9876', (string) $row['openai_api_key'], 'the key is stored encrypted');
        self::assertSame('sk-proj-abcdef9876', static::getContainer()->get(OpenAiKeys::class)->keyFor('ACME0001'), 'the account\'s calls use its own key');

        $removed = $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['openai_api_key' => null], as: $owner));
        self::assertFalse($removed['openai_api_key_set']);
        self::assertSame('', static::getContainer()->get(OpenAiKeys::class)->keyFor('ACME0001'), 'without its key, the platform key (empty in tests)');
    }

    public function testAReadOnlyUserSeesTheSettingsButCannotChangeOrCheckThem(): void
    {
        $owner = $this->account('ACME0001');
        $this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', self::SERVER, as: $owner);
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        self::assertSame('smtp.acme.test', $this->data($this->api('GET', '/api/v1/customer/ACME0001/system-settings', as: 'reader@acme.test'))['smtp_host']);
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['smtp_port' => 25], as: 'reader@acme.test'), 403, 'FORBIDDEN');
        $this->assertApiError($this->api('POST', '/api/v1/customer/ACME0001/system-settings/smtp-check', [], as: 'reader@acme.test'), 403, 'FORBIDDEN');
    }

    public function testAnotherTenantGetsNotFoundAndAnonymousGetsUnauthorized(): void
    {
        $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');

        $this->assertApiError($this->api('GET', '/api/v1/customer/ACME0001/system-settings', as: $globex), 404, 'CUSTOMER_NOT_FOUND', 'another tenant\'s id is 404, never 403');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['smtp_port' => 25], as: $globex), 404, 'CUSTOMER_NOT_FOUND');
        $this->assertApiError($this->api('POST', '/api/v1/customer/ACME0001/system-settings/smtp-check', self::SERVER, as: $globex), 404, 'CUSTOMER_NOT_FOUND');
        self::assertSame(401, $this->api('GET', '/api/v1/customer/ACME0001/system-settings')['status']);
    }

    public function testTheCheckSendsATestEmailThroughTheFormsServerToTheUserWithoutSavingIt(): void
    {
        $owner = $this->account('ACME0001', language: 'en-US');

        $result = $this->api('POST', '/api/v1/customer/ACME0001/system-settings/smtp-check', self::SERVER, as: $owner);

        self::assertSame(200, $result['status'], $result['body']);
        self::assertCount(1, $this->transports()->sent);
        $sent = $this->transports()->sent[0];
        self::assertSame('smtp.acme.test', $sent['server']->host);
        self::assertSame(self::FIXTURE_SMTP_PASS, $sent['server']->password, 'the password typed in the form');
        self::assertSame([$owner], array_map(static fn (Address $a): string => $a->getAddress(), $sent['email']->getTo()), 'sent to the user who pressed Validate');
        self::assertSame('hello@acme.test', $sent['email']->getFrom()[0]->getAddress(), 'from the server\'s sender');
        self::assertSame('Your Mappi SMTP server works', $sent['email']->getSubject(), 'in the account\'s language');
        self::assertStringContainsString('smtp.acme.test', (string) $sent['email']->getHtmlBody());
        self::assertNull($this->em()->find(SystemSettings::class, 'ACME0001'), 'a check saves nothing');
    }

    public function testTheCheckUsesTheSavedPasswordWhenTheFormSendsNone(): void
    {
        $owner = $this->account('ACME0001');
        $this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', self::SERVER, as: $owner);
        $form = self::SERVER;
        unset($form['smtp_password']);

        $this->api('POST', '/api/v1/customer/ACME0001/system-settings/smtp-check', $form, as: $owner);

        self::assertSame(self::FIXTURE_SMTP_PASS, $this->transports()->sent[0]['server']->password ?? null, 'the saved password is never sent back to the browser, so the check reuses it');
    }

    public function testAFailedCheckSaysWhereItFailedWithoutEchoingTheServer(): void
    {
        $owner = $this->account('ACME0001');
        $this->transports()->failWith = 'Failed to authenticate on SMTP server with username "mailer@acme.test" using the following authenticators: "LOGIN". 535 5.7.8 internal-banner';

        $result = $this->api('POST', '/api/v1/customer/ACME0001/system-settings/smtp-check', self::SERVER, as: $owner);

        $this->assertApiError($result, 502, 'SMTP_CHECK_FAILED');
        self::assertSame('authentication', $result['json']['error']['details']['reason'] ?? null);
        self::assertStringNotContainsString('internal-banner', $result['body'], 'the server\'s answer is not echoed back');
    }

    public function testAnAccountsEmailsGoThroughItsOwnServerAndOthersThroughThePlatform(): void
    {
        $owner = $this->account('ACME0001');
        $this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', self::SERVER, as: $owner);
        $servers = static::getContainer()->get(CustomerMailServers::class);

        self::assertSame(self::FIXTURE_SMTP_PASS, $servers->serverFor('ACME0001')?->password, 'the mailer opens the account\'s server');
        self::assertNull($servers->serverFor('GLOBEX01'), 'an account without its own server uses the platform one');
    }
}
