<?php

namespace App\Tests\Functional\Api\Identity;

use App\Identity\Domain\Model\Customer;
use App\Identity\Domain\Model\User;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Mime\Email;

final class RegisterTest extends ApiTestCase
{
    private const VALID = ['email' => 'Nueva@Example.COM ', 'password' => 'correct-horse', 'name' => 'Nora Nueva'];

    public function testSignUpCreatesTheAccountItsRootUserAndAMonthOfStarter(): void
    {
        $this->clock()->set('2026-01-31T10:00:00Z');

        $data = $this->data($this->api('POST', '/api/v1/register', self::VALID + ['language' => 'en-US']), 201);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9]{8}$/', $data['customer_id'], 'PRD §6.1: customer_id is 8 random alphanumerics');
        self::assertSame(['email' => 'nueva@example.com', 'name' => 'Nora Nueva', 'root' => true, 'role' => 'Customer-Admin'], $data['user'], 'PRD §8.2: the email is normalized to lowercase and the user is the root Customer-Admin');

        $customer = $this->em()->find(Customer::class, $data['customer_id']);
        self::assertNotNull($customer);
        self::assertFalse($customer->onboardingCompleted(), 'PRD §7.3: a new account has onboarding pending');
        self::assertSame('en-US', $customer->language());
        self::assertSame('default', $customer->source(), 'PRD §8.2: source defaults to "default"');
    }

    public function testTheNewUserCanSignInWithThePasswordRightAway(): void
    {
        $this->data($this->api('POST', '/api/v1/register', self::VALID), 201);

        $tokens = $this->data($this->api('POST', '/api/v1/auth/token', ['email' => 'nueva@example.com', 'password' => 'correct-horse']));

        self::assertNotEmpty($tokens['id_token'], 'PRD §8.2: register returns no tokens, the client then signs in');
    }

    public function testLanguageDefaultsToSpanishAndSourceIsKept(): void
    {
        $data = $this->data($this->api('POST', '/api/v1/register', self::VALID + ['source' => 'shopify']), 201);

        $customer = $this->em()->find(Customer::class, $data['customer_id']);
        self::assertNotNull($customer);
        self::assertSame(['es-CO', 'shopify'], [$customer->language(), $customer->source()], 'PRD §8.2: language defaults to es-CO');
    }

    public function testAnEmailAlreadyInUseAnywhereIsAConflict(): void
    {
        $this->account('ACME0001');

        $response = $this->api('POST', '/api/v1/register', ['email' => 'ROOT@acme0001.test', 'password' => 'correct-horse', 'name' => 'Copy']);

        $this->assertApiError($response, 409, 'EMAIL_ALREADY_EXISTS', 'PRD §4.3: console emails are unique across the whole system');
    }

    public function testShapeChecksAreValidationErrors(): void
    {
        $this->assertApiError($this->api('POST', '/api/v1/register', ['email' => 'not-an-email', 'password' => 'correct-horse', 'name' => 'X']), 400, 'VALIDATION_ERROR', 'D13: one email rule');
        $this->assertApiError($this->api('POST', '/api/v1/register', ['email' => 'a@b.co', 'password' => 'short', 'name' => 'X']), 400, 'VALIDATION_ERROR', 'PRD §13.1: at least 8 characters');
        $this->assertApiError($this->api('POST', '/api/v1/register', ['email' => 'a@b.co', 'password' => 'correct-horse', 'name' => str_repeat('n', 51)]), 400, 'VALIDATION_ERROR', 'PRD §6.2: name 1–50');
        $this->assertApiError($this->api('POST', '/api/v1/register', ['email' => 'a@b.co', 'password' => 'correct-horse', 'name' => 'X', 'language' => 'fr-FR']), 400, 'VALIDATION_ERROR', 'PRD §6.1: es-CO or en-US');
    }

    public function testTheWelcomeEmailIsSentInTheAccountLanguageWithACopyToSupport(): void
    {
        $this->api('POST', '/api/v1/register', self::VALID + ['language' => 'en-US']);

        $welcome = $this->welcomeEmail();
        self::assertNotNull($welcome, 'D20: the welcome email is sent on sign-up');
        self::assertSame('nueva@example.com', $welcome->getTo()[0]->getAddress());
        self::assertSame('support@mappi.test', $welcome->getBcc()[0]->getAddress(), 'PRD §7.21: the welcome email has a BCC to support');
        self::assertStringContainsString('Welcome to Mappi', (string) $welcome->getSubject(), 'PRD §7.21: in the account language (en)');
    }

    public function testSigningUpInSpanishSendsTheSpanishWelcome(): void
    {
        $this->api('POST', '/api/v1/register', self::VALID);

        $welcome = $this->welcomeEmail();
        self::assertNotNull($welcome);
        self::assertStringContainsString('Bienvenido a Mappi', (string) $welcome->getSubject());
    }

    public function testSignUpIsRecordedAsUserRootRegistered(): void
    {
        $data = $this->data($this->api('POST', '/api/v1/register', self::VALID), 201);

        $count = (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM domain_event_log WHERE event_type = 'UserRootRegistered' AND customer_id = ?", [$data['customer_id']]);
        self::assertSame(1, $count, 'PRD §8.2, §12: UserRootRegistered');
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'nueva@example.com']);
        self::assertNotNull($user);
        self::assertTrue($user->isRoot());
    }

    private function welcomeEmail(): ?Email
    {
        foreach ($this->getMailerMessages() as $message) {
            if ($message instanceof Email && [] !== $message->getBcc()) {
                return $message;
            }
        }

        return null;
    }
}
