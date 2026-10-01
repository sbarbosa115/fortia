<?php

namespace App\Tests\Functional\Api\Identity;

use App\Identity\Domain\Model\Customer;
use App\Tests\Support\ApiTestCase;

/** PRD §8.3 GET /customer/{customer_id}/settings: public, what the respondent app needs (§9.9, §9.15, §9.16). */
final class CustomerSettingsTest extends ApiTestCase
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
}
