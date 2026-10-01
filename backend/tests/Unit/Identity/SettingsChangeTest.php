<?php

namespace App\Tests\Unit\Identity;

use App\Identity\Domain\Model\Customer;
use App\Identity\Domain\Model\SettingsChange;
use PHPUnit\Framework\TestCase;

final class SettingsChangeTest extends TestCase
{
    public function testEmptyTrackingIdsClearThem(): void
    {
        $change = SettingsChange::of(['pixel_id' => '', 'google_ads_id' => '  ', 'linkedin_partner_id' => 'LI-1']);

        self::assertSame(['pixel_id' => null, 'google_ads_id' => null, 'linkedin_partner_id' => 'LI-1'], $change->changes(), 'PRD §8.3: tracking ids, empty or null clears them');
    }

    public function testANullMaxFilesGoesBackToTheDefault(): void
    {
        self::assertSame(['max_files' => Customer::DEFAULT_MAX_FILES], SettingsChange::of(['max_files' => null])->changes(), 'PRD §8.3: max_files null resets it');
    }

    public function testTheCustomerKeepsWhatWasNotChanged(): void
    {
        $customer = new Customer('ACME0001', 'es-CO', 'default', new \DateTimeImmutable());
        $customer->changeSettings(SettingsChange::of(['pixel_id' => 'PX', 'max_files' => 4])->changes(), new \DateTimeImmutable());

        $customer->changeSettings(SettingsChange::of(['language' => 'en-US', 'max_files' => null])->changes(), new \DateTimeImmutable());

        self::assertSame(['en-US', 'PX', 10], [$customer->language(), $customer->settings()['pixel_id'], $customer->settings()['max_files']]);
    }
}
