<?php

namespace App\Tests\Functional\Api\Billing;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\Mime\Email;

/** PRD §8.3 POST /contact and §7.21 "Sales lead" (recipients as configuration, D8; texts in i18n, D23). */
final class ContactTest extends ApiTestCase
{
    public function testASalesLeadIsEmailedToTheConfiguredRecipientsInSpanish(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter', language: 'en-US');

        $response = $this->api('POST', '/api/v1/contact', ['type' => 'plan', 'plan_id' => 'enterprise', 'email' => 'buyer@globex.test', 'phone' => '+57 300 123 4567'], as: $email);

        self::assertSame(200, $response['status'], $response['body']);
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('[Ventas] Test User está interesado en el plan Enterprise', $message->getSubject(), 'PRD §7.21 subject, always in Spanish');
        self::assertSame(explode(',', (string) ($_SERVER['SALES_LEAD_RECIPIENTS'] ?? $_ENV['SALES_LEAD_RECIPIENTS'] ?? 'sales@mappi.test')), array_map(static fn ($a) => $a->getAddress(), $message->getTo()), 'D8: SALES_LEAD_RECIPIENTS, not hard-coded');
        $body = (string) $message->getHtmlBody();
        foreach (['buyer@globex.test', '+57 300 123 4567', 'GLOBEX01', 'Enterprise', $email] as $expected) {
            self::assertStringContainsString($expected, $body);
        }
    }

    public function testThePlanMustExist(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');

        $this->assertApiError($this->api('POST', '/api/v1/contact', ['type' => 'plan', 'plan_id' => 'ghost', 'email' => 'a@b.co', 'phone' => '123'], as: $email), 404, 'PLAN_NOT_FOUND');
        self::assertEmailCount(0);
    }

    public function testTheInputIsValidated(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $valid = ['type' => 'plan', 'plan_id' => 'enterprise', 'email' => 'a@b.co', 'phone' => '123'];

        foreach ([
            'bad email' => ['email' => 'not-an-email'],
            'empty phone' => ['phone' => ''],
            'long phone' => ['phone' => str_repeat('1', 51)],
            'unknown type' => ['type' => 'demo'],
            'plan required for type plan' => ['plan_id' => null],
        ] as $case => $change) {
            $this->assertApiError($this->api('POST', '/api/v1/contact', array_merge($valid, $change), as: $email), 400, 'VALIDATION_ERROR', $case);
        }
        $this->assertApiError($this->api('POST', '/api/v1/contact', $valid), 401, 'UNAUTHORIZED');
    }
}
