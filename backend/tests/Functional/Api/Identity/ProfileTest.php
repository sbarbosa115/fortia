<?php

namespace App\Tests\Functional\Api\Identity;

use App\Branding\Domain\Model\CustomerStyles;
use App\Identity\Domain\Model\Customer;
use App\Tests\Support\ApiTestCase;

final class ProfileTest extends ApiTestCase
{
    public function testTheProfileShowsTheAccountTheUserAndTheBrand(): void
    {
        $this->account('ACME0001', language: 'en-US');
        $this->user('ACME0001', 'ana@acme.test', name: 'Ana');
        $this->em()->persist(new CustomerStyles('ACME0001', 'https://acme.test', ['logoUrl' => 'https://acme.test/logo.png', 'font' => 'Inter'], new \DateTimeImmutable()));
        $this->em()->flush();

        $customer = $this->data($this->api('GET', '/api/v1/profile', as: 'ana@acme.test'))['customer'];

        self::assertSame([
            'customer_id' => 'ACME0001',
            'name' => 'Ana',
            'email' => 'ana@acme.test',
            'language' => 'en-US',
            'logo_url' => 'https://acme.test/logo.png',
            'website' => 'https://acme.test',
            'styles' => ['logoUrl' => 'https://acme.test/logo.png', 'font' => 'Inter'],
        ], $customer, 'PRD §8.2 GET /profile');
    }

    public function testAnAccountWithoutBrandFallsBackToItsWorkspaceWebsite(): void
    {
        $owner = $this->account('ACME0001');
        $customer = $this->em()->find(Customer::class, 'ACME0001');
        self::assertNotNull($customer);
        $customer->describeWorkspace('Acme', 'https://acme.example', new \DateTimeImmutable());
        $this->em()->flush();

        $profile = $this->data($this->api('GET', '/api/v1/profile', as: $owner))['customer'];

        self::assertSame(['https://acme.example', null, null], [$profile['website'], $profile['logo_url'], $profile['styles']]);
    }

    public function testTheProfileNeedsASignIn(): void
    {
        $this->assertApiError($this->api('GET', '/api/v1/profile'), 401, 'UNAUTHORIZED');
    }
}
