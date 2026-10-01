<?php

namespace App\Tests\Functional\Api\Identity;

use App\Identity\Domain\Model\Customer;
use App\Tests\Support\ApiTestCase;

final class WorkspaceTest extends ApiTestCase
{
    public function testOnboardingStepTwoSavesTheWorkspace(): void
    {
        $owner = $this->account('NEWCO001', onboarding: false);

        $data = $this->data($this->api('PATCH', '/api/v1/customer/workspace', ['name' => 'Newco', 'language' => 'en-US', 'website' => 'https://newco.test'], as: $owner));

        self::assertSame(['name' => 'Newco', 'language' => 'en-US', 'website' => 'https://newco.test'], $data, 'D15: the workspace is saved');
        $customer = $this->em()->find(Customer::class, 'NEWCO001');
        self::assertNotNull($customer);
        $this->em()->refresh($customer);
        self::assertSame(['Newco', 'https://newco.test', 'en-US'], [$customer->workspaceName(), $customer->website(), $customer->settings()['language']], 'D15: the workspace is saved');
    }

    public function testFieldsNotSentKeepTheirValue(): void
    {
        $owner = $this->account('NEWCO001');
        $this->api('PATCH', '/api/v1/customer/workspace', ['name' => 'Newco', 'website' => 'https://newco.test'], as: $owner);

        $data = $this->data($this->api('PATCH', '/api/v1/customer/workspace', ['website' => null], as: $owner));

        self::assertSame(['name' => 'Newco', 'language' => 'es-CO', 'website' => null], $data);
    }

    public function testShapeRules(): void
    {
        $owner = $this->account('NEWCO001');

        $this->assertApiError($this->api('PATCH', '/api/v1/customer/workspace', [], as: $owner), 400, 'VALIDATION_ERROR', 'at least one field');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/workspace', ['website' => 'not a url'], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/workspace', ['language' => 'xx'], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('PATCH', '/api/v1/customer/workspace', ['plan' => 'pro'], as: $owner), 400, 'VALIDATION_ERROR', 'no extra fields');
    }

    public function testReadOnlyMembersCannotDescribeTheWorkspace(): void
    {
        $this->account('NEWCO001');
        $this->user('NEWCO001', 'reader@newco.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('PATCH', '/api/v1/customer/workspace', ['name' => 'X'], as: 'reader@newco.test'), 403, 'FORBIDDEN');
    }
}
