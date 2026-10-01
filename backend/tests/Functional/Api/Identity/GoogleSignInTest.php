<?php

namespace App\Tests\Functional\Api\Identity;

use App\Billing\Domain\Model\CustomerPlan;
use App\Identity\Domain\Model\Customer;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Google\FakeGoogleIdentity;
use App\Tests\Support\ApiTestCase;

/**
 * Google sign-in (PRD §13.1): OAuth code + PKCE. The test container swaps the Google adapter for FakeGoogleIdentity,
 * whose authorization codes are "fake|<email>|<name>|<locale>|<subject>".
 */
final class GoogleSignInTest extends ApiTestCase
{
    public function testTheConsoleGetsTheAuthorizationUrlWithItsStateAndChallenge(): void
    {
        $data = $this->data($this->api('GET', '/api/v1/auth/google/authorize?state=abc123&code_challenge=xyz'));

        self::assertStringContainsString('state=abc123', $data['authorization_url']);
        self::assertStringContainsString('code_challenge=xyz', $data['authorization_url']);
        self::assertStringContainsString(urlencode('/console/sign-in'), $data['authorization_url'], 'Google returns to the console\'s /sign-in');
    }

    public function testWithoutAGoogleClientTheProviderIsNotConfigured(): void
    {
        $this->google()->configured = false;

        $this->assertApiError($this->api('GET', '/api/v1/auth/google/authorize?state=a&code_challenge=b'), 503, 'PROVIDER_NOT_CONFIGURED', 'PRD §10.2: "Sign-in is temporarily unavailable…"');
        $this->assertApiError($this->api('POST', '/api/v1/auth/google/token', ['code' => 'fake|a@b.co|A|en|1', 'code_verifier' => 'v']), 503, 'PROVIDER_NOT_CONFIGURED');
    }

    public function testTheFirstGoogleLoginCreatesTheAccountAndSignsIn(): void
    {
        $this->clock()->set('2026-05-10T09:00:00Z');

        $tokens = $this->data($this->api('POST', '/api/v1/auth/google/token', ['code' => 'fake|Gina@Gmail.com|Gina G|en-GB|g-1', 'code_verifier' => 'verifier']));

        self::assertNotEmpty($tokens['id_token']);
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'gina@gmail.com']);
        self::assertNotNull($user);
        self::assertTrue($user->isRoot());
        self::assertSame(['Customer-Admin'], $user->groups(), 'PRD §13.1: first Google login creates the root Customer-Admin');
        $customer = $this->em()->find(Customer::class, $user->customerId());
        self::assertSame(['en-US', false], [$customer?->language(), $customer?->onboardingCompleted()], 'PRD §13.1: language = Google\'s, onboarding pending');
        $plan = $this->em()->find(CustomerPlan::class, $user->customerId());
        self::assertSame(['starter', '2026-05-10', '2026-06-10'], [$plan?->planId(), $plan?->fromAt(), $plan?->toAt()], 'PRD §16.3 #1: a 1-month starter plan');
    }

    public function testAnyOtherGoogleLanguageGivesASpanishAccount(): void
    {
        $this->data($this->api('POST', '/api/v1/auth/google/token', ['code' => 'fake|pt@gmail.com|Pê|pt-BR|g-2', 'code_verifier' => 'v']));

        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'pt@gmail.com']);
        self::assertSame('es-CO', $this->em()->find(Customer::class, (string) $user?->customerId())?->language(), 'PRD §13.1: Google\'s language, or es-CO');
    }

    public function testTheSecondGoogleLoginSignsIntoTheSameAccount(): void
    {
        $this->api('POST', '/api/v1/auth/google/token', ['code' => 'fake|gina@gmail.com|Gina|en|g-1', 'code_verifier' => 'v']);

        $this->data($this->api('POST', '/api/v1/auth/google/token', ['code' => 'fake|gina@gmail.com|Gina|en|g-1', 'code_verifier' => 'v']));

        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM app_user WHERE email = 'gina@gmail.com'"));
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM domain_event_log WHERE event_type = 'UserRootRegistered'"), 'only the first login registers an account');
    }

    public function testAPasswordAccountIsLinkedAndTheUserIsAskedToRetry(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('POST', '/api/v1/auth/google/token', ['code' => 'fake|'.$owner.'|Ana|es|g-9', 'code_verifier' => 'v']), 409, 'EMAIL_LINKED_RETRY_LOGIN', 'PRD §13.1: links and asks to retry');

        $tokens = $this->data($this->api('POST', '/api/v1/auth/google/token', ['code' => 'fake|'.$owner.'|Ana|es|g-9', 'code_verifier' => 'v']));
        self::assertNotEmpty($tokens['id_token'], 'the retry signs in');
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM customer WHERE customer_id <> 'ACME0001'"), 'no second account for a linked email');
    }

    public function testACodeGoogleRefusesIsNotASignIn(): void
    {
        $this->assertApiError($this->api('POST', '/api/v1/auth/google/token', ['code' => 'rejected', 'code_verifier' => 'v']), 401, 'GOOGLE_SIGN_IN_FAILED');
        $this->assertApiError($this->api('POST', '/api/v1/auth/google/token', ['code' => '', 'code_verifier' => 'v']), 400, 'VALIDATION_ERROR');
    }

    private function google(): FakeGoogleIdentity
    {
        return static::getContainer()->get(FakeGoogleIdentity::class);
    }
}
