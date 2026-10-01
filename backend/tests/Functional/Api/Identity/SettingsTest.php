<?php

namespace App\Tests\Functional\Api\Identity;

use App\Identity\Domain\Model\Customer;
use App\Tests\Support\ApiTestCase;

/**
 * PRD §8.3 GET/PATCH /customer/{customer_id}/settings: public to read (the respondent app, §9.9, §9.15, §9.16), AG to
 * change.
 */
final class SettingsTest extends ApiTestCase
{
    public function testAnyoneCanReadAnAccountsSettingsWithTheDefaults(): void
    {
        $this->account('ACME0001', language: 'en-US');

        $settings = $this->data($this->api('GET', '/api/v1/customer/ACME0001/settings'));

        self::assertSame([
            'language' => 'en-US',
            'transcription_url' => null,
            'pixel_id' => null,
            'linkedin_partner_id' => null,
            'linkedin_conversion_id' => null,
            'google_ads_id' => null,
            'google_ads_conversion_label' => null,
            'max_files' => 10,
        ], $settings, 'PRD §8.3: public, CustomerSettings with max_files 10 by default');
    }

    public function testTheRespondentAppReadsTheTrackingIdsAndTheFileLimit(): void
    {
        $this->account('ACME0001');
        $customer = $this->em()->find(Customer::class, 'ACME0001');
        self::assertNotNull($customer);
        $customer->changeSettings(['pixel_id' => '1234', 'max_files' => 3, 'google_ads_id' => ''], new \DateTimeImmutable());
        $this->em()->flush();

        $settings = $this->data($this->api('GET', '/api/v1/customer/ACME0001/settings'));

        self::assertSame('1234', $settings['pixel_id'], '§9.16 the account\'s pixel_id');
        self::assertSame(3, $settings['max_files'], '§9.9 max files = the account\'s settings.max_files');
        self::assertNull($settings['google_ads_id'], 'an empty tracking id reads as null');
    }

    public function testAnUnknownAccountIsNotFound(): void
    {
        $this->assertApiError($this->api('GET', '/api/v1/customer/NOPE0000/settings'), 404, 'CUSTOMER_NOT_FOUND');
    }

    public function testAnAdminChangesOnlyTheFieldsSent(): void
    {
        $owner = $this->account('ACME0001');
        $this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['pixel_id' => '1234', 'google_ads_id' => 'AW-1'], as: $owner);

        $settings = $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['max_files' => 5, 'pixel_id' => ''], as: $owner));

        self::assertSame(5, $settings['max_files']);
        self::assertNull($settings['pixel_id'], 'PRD §8.3: an empty tracking id clears it');
        self::assertSame('AW-1', $settings['google_ads_id'], 'a field not sent keeps its value');
        self::assertSame($settings, $this->data($this->api('GET', '/api/v1/customer/ACME0001/settings')));
    }

    public function testMaxFilesIsAStrictIntegerFromOneToTwentyAndNullResetsIt(): void
    {
        $owner = $this->account('ACME0001');

        foreach ([0, 21, '5', 2.5, true] as $bad) {
            $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['max_files' => $bad], as: $owner), 400, 'VALIDATION_ERROR', 'PRD §8.3: max_files is a strict integer 1–20, got '.json_encode($bad));
        }
        $this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['max_files' => 20], as: $owner);

        self::assertSame(10, $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['max_files' => null], as: $owner))['max_files'], 'PRD §8.3: null resets it to the default');
    }

    public function testTheBodyMustChangeSomethingKnownAndLanguageIsNeverNull(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/settings', [], as: $owner), 400, 'VALIDATION_ERROR', 'PRD §8.3: at least one field');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['theme' => 'dark'], as: $owner), 400, 'VALIDATION_ERROR', 'PRD §8.3: no extra fields');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['language' => null], as: $owner), 400, 'VALIDATION_ERROR', 'PRD §8.3: language is never null');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['language' => 'pt-BR'], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['pixel_id' => str_repeat('9', 65)], as: $owner), 400, 'VALIDATION_ERROR', 'PRD §6.1: tracking ids ≤ 64');
    }

    public function testEveryChangeOfSettingsPublishesProfileEdited(): void
    {
        $owner = $this->account('ACME0001');

        $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['language' => 'en-US'], as: $owner));

        $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['language' => 'es-CO', 'max_files' => 3], as: $owner));
        self::assertSame(2, (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM domain_event_log WHERE event_type = 'ProfileEdited' AND customer_id = 'ACME0001'"), 'PRD §8.3: ProfileEdited');
    }

    public function testOnlyAdminGroupsMayChangeSettings(): void
    {
        $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['language' => 'en-US'], as: 'reader@acme.test'), 403, 'FORBIDDEN', 'PRD §8.3: AG');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['language' => 'en-US']), 401, 'UNAUTHORIZED');
    }

    public function testAnotherTenantsSettingsAreNotFoundButAnAdminMayChangeThem(): void
    {
        $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $admin = $this->admin();

        $this->assertApiError($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['language' => 'en-US'], as: $globex), 404, 'CUSTOMER_NOT_FOUND', 'another tenant\'s account is 404, never 403');
        self::assertSame(7, $this->data($this->api('PATCH', '/api/v1/customer/ACME0001/settings', ['max_files' => 7], as: $admin))['max_files'], 'PRD §8.3: an Admin may change any account');
    }
}
