<?php

namespace App\Tests\Functional\Api\Identity;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Model\User;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Mime\Email;

final class PasswordRecoveryTest extends ApiTestCase
{
    public function testTheAnswerIsTheSameWhetherTheAccountExistsOrNot(): void
    {
        $email = $this->account('ACME0001');

        $known = $this->api('POST', '/api/v1/password-recovery', ['email' => $email]);
        $unknown = $this->api('POST', '/api/v1/password-recovery', ['email' => 'ghost@nowhere.test']);

        self::assertSame(200, $known['status'], $known['body']);
        self::assertSame(200, $unknown['status'], $unknown['body']);
        self::assertSame($known['json'], $unknown['json'], 'PRD §8.2, §14.2: never reveal which accounts exist');
    }

    public function testACodeArrivesByEmailWithALinkToTheResetScreen(): void
    {
        $email = $this->account('ACME0001', language: 'en-US');

        $this->api('POST', '/api/v1/password-recovery', ['email' => $email]);

        $message = $this->recoveryEmail();
        self::assertSame($email, $message->getTo()[0]->getAddress());
        self::assertMatchesRegularExpression('#/reset-password\?code=\d{6}#', (string) $message->getHtmlBody(), 'PRD §7.21: code + link {ADMIN_URL}/reset-password?code=####');
    }

    public function testNoEmailIsSentForAnUnknownAccount(): void
    {
        $this->api('POST', '/api/v1/password-recovery', ['email' => 'ghost@nowhere.test']);

        self::assertCount(0, $this->getMailerMessages(), 'nothing to send to an account that does not exist');
    }

    public function testTheRightCodeSetsTheNewPassword(): void
    {
        $email = $this->account('ACME0001');
        $this->api('POST', '/api/v1/password-recovery', ['email' => $email]);
        $code = $this->codeFromEmail();

        $response = $this->api('POST', '/api/v1/password-recovery/confirm', ['email' => strtoupper($email), 'code' => $code, 'password' => 'brand-new-pass']);

        self::assertSame(200, $response['status'], $response['body']);
        $this->data($this->api('POST', '/api/v1/auth/token', ['email' => $email, 'password' => 'brand-new-pass']));
        $this->assertApiError($this->api('POST', '/api/v1/password-recovery/confirm', ['email' => $email, 'code' => $code, 'password' => 'another-pass']), 400, 'INVALID_RESET_CODE', 'a code is used once');
    }

    public function testAWrongCodeOrAnUnknownUserIsAnInvalidCode(): void
    {
        $email = $this->account('ACME0001');
        $this->api('POST', '/api/v1/password-recovery', ['email' => $email]);

        $this->assertApiError($this->api('POST', '/api/v1/password-recovery/confirm', ['email' => $email, 'code' => '000000x', 'password' => 'brand-new-pass']), 400, 'INVALID_RESET_CODE');
        $this->assertApiError($this->api('POST', '/api/v1/password-recovery/confirm', ['email' => 'ghost@nowhere.test', 'code' => '123456', 'password' => 'brand-new-pass']), 400, 'INVALID_RESET_CODE', 'PRD §8.2: also if the user does not exist');
    }

    public function testACodeExpiresAfterAnHour(): void
    {
        $email = $this->account('ACME0001');
        $this->clock()->set('2026-03-01T10:00:00Z');
        $this->api('POST', '/api/v1/password-recovery', ['email' => $email]);
        $code = $this->codeFromEmail();

        $this->clock()->set('2026-03-01T11:00:01Z');

        $this->assertApiError($this->api('POST', '/api/v1/password-recovery/confirm', ['email' => $email, 'code' => $code, 'password' => 'brand-new-pass']), 400, 'EXPIRED_RESET_CODE');
    }

    public function testAShortPasswordIsAnInvalidPasswordAndKeepsTheCode(): void
    {
        $email = $this->account('ACME0001');
        $this->api('POST', '/api/v1/password-recovery', ['email' => $email]);
        $code = $this->codeFromEmail();

        $this->assertApiError($this->api('POST', '/api/v1/password-recovery/confirm', ['email' => $email, 'code' => $code, 'password' => 'short']), 400, 'INVALID_PASSWORD', 'PRD §13.1: minimum 8 characters');
        self::assertSame(200, $this->api('POST', '/api/v1/password-recovery/confirm', ['email' => $email, 'code' => $code, 'password' => 'long-enough'])['status'], 'the code is still good after a password the policy refused');
    }

    public function testFiveWrongCodesBurnTheCode(): void
    {
        $email = $this->account('ACME0001');
        $this->api('POST', '/api/v1/password-recovery', ['email' => $email]);
        $code = $this->codeFromEmail();
        for ($i = 0; $i < 5; ++$i) {
            // Each from another address, so the request rate limit is not what stops them.
            self::assertSame(400, $this->confirmFrom('10.0.0.'.$i, ['email' => $email, 'code' => 'wrong'.$i, 'password' => 'brand-new-pass']));
        }

        self::assertSame(429, $this->confirmFrom('10.0.1.1', ['email' => $email, 'code' => $code, 'password' => 'brand-new-pass']), 'PRD §13.1: recovery has an attempt limit, even with the right code afterwards');
        self::assertSame('TOO_MANY_ATTEMPTS', json_decode((string) $this->client->getResponse()->getContent(), true)['error']['code'] ?? null);
    }

    public function testConfirmationsAreRateLimitedPerEmailAndAddress(): void
    {
        $email = $this->account('ACME0001');
        for ($i = 0; $i < 5; ++$i) {
            $this->api('POST', '/api/v1/password-recovery/confirm', ['email' => $email, 'code' => 'x', 'password' => 'brand-new-pass']);
        }

        $this->assertApiError($this->api('POST', '/api/v1/password-recovery/confirm', ['email' => $email, 'code' => 'x', 'password' => 'brand-new-pass']), 429, 'TOO_MANY_ATTEMPTS');
    }

    /** @param array<string, string> $body */
    private function confirmFrom(string $ip, array $body): int
    {
        $this->client->request('POST', '/api/v1/password-recovery/confirm', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $ip], content: (string) json_encode($body));

        return $this->client->getResponse()->getStatusCode();
    }

    public function testRequestsAreRateLimited(): void
    {
        $email = $this->account('ACME0001');
        for ($i = 0; $i < 5; ++$i) {
            $this->api('POST', '/api/v1/password-recovery', ['email' => $email]);
        }

        $this->assertApiError($this->api('POST', '/api/v1/password-recovery', ['email' => $email]), 429, 'TOO_MANY_ATTEMPTS');
    }

    public function testAGoogleOnlyUserCanSetAPasswordThroughRecovery(): void
    {
        $this->account('ACME0001');
        $this->user('ACME0001', 'google@acme.test');
        $this->api('POST', '/api/v1/password-recovery', ['email' => 'google@acme.test']);

        self::assertSame(200, $this->api('POST', '/api/v1/password-recovery/confirm', ['email' => 'google@acme.test', 'code' => $this->codeFromEmail(), 'password' => 'first-password'])['status']);
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'google@acme.test']);
        self::assertNotNull($user);
        $this->em()->refresh($user);
        self::assertTrue(static::getContainer()->get(PasswordHasher::class)->verify((string) $user->passwordHash(), 'first-password'));
    }

    private function recoveryEmail(): Email
    {
        $messages = $this->getMailerMessages();
        self::assertNotEmpty($messages, 'a recovery email was sent');
        $message = end($messages);
        self::assertInstanceOf(Email::class, $message);

        return $message;
    }

    private function codeFromEmail(): string
    {
        self::assertSame(1, preg_match('#code=(\d{6})#', (string) $this->recoveryEmail()->getHtmlBody(), $m));

        return $m[1];
    }
}
