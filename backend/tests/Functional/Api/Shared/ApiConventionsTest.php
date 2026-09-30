<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Shared;

use App\Tests\Support\ApiTestCase;

/** The conventions of PRD §8.1 and §4.4 that every endpoint inherits from the shared kernel. */
final class ApiConventionsTest extends ApiTestCase
{
    public function testAnEndpointThatNeedsAUserAnswers401WithoutOne(): void
    {
        $this->assertApiError($this->api('GET', '/api/v1/customer/onboarding'), 401, 'UNAUTHORIZED');
    }

    public function testAMalformedTokenIs401(): void
    {
        $this->assertApiError($this->api('GET', '/api/v1/customer/onboarding', headers: ['Authorization' => 'Bearer not.a.jwt']), 401, 'UNAUTHORIZED');
    }

    public function testABodyThatIsNotJsonIsInvalidJson(): void
    {
        $email = $this->account('ACME0001');

        $this->assertApiError($this->api('PATCH', '/api/v1/customer/onboarding', '{bad', as: $email), 400, 'INVALID_JSON');
    }

    public function testTypesAreStrictAndMessagesAreFlattened(): void
    {
        $email = $this->account('ACME0001');

        $response = $this->api('PATCH', '/api/v1/customer/onboarding', ['completed' => 'yes'], as: $email);

        $this->assertApiError($response, 400, 'VALIDATION_ERROR', '"yes" is not a bool');
        self::assertStringStartsWith('completed: ', $response['json']['error']['message'], 'PRD §8.1: "field: msg; ..."');
    }

    public function testExtraFieldsAreRefusedWhereTheContractSaysSo(): void
    {
        $email = $this->account('ACME0001');

        $response = $this->api('PATCH', '/api/v1/customer/onboarding', ['completed' => true, 'extra' => 1], as: $email);

        $this->assertApiError($response, 400, 'VALIDATION_ERROR');
        self::assertStringContainsString('extra', $response['json']['error']['message']);
    }

    public function testAnAdminAssumesACustomerAndActsAsItsRootUser(): void
    {
        $admin = $this->admin();
        $this->account('GLOBEX01', plan: 'starter');

        $usage = $this->data($this->api('GET', '/api/v1/customer/usage', as: $admin, headers: ['X-Assume-Customer-Id' => 'GLOBEX01']));

        self::assertSame('starter', $usage['customer_plan']['plan_id'], 'PRD §4.4: the request runs as the assumed account');
    }

    public function testTheAssumeHeaderNameIsCaseInsensitive(): void
    {
        $admin = $this->admin();
        $this->account('GLOBEX01', plan: 'starter');

        $usage = $this->data($this->api('GET', '/api/v1/customer/usage', as: $admin, headers: ['x-assume-customer-id' => 'GLOBEX01']));

        self::assertSame('starter', $usage['customer_plan']['plan_id']);
    }

    public function testOnlyAnAdminMayAssume(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01', plan: 'starter');

        $this->assertApiError($this->api('GET', '/api/v1/customer/usage', as: $owner, headers: ['X-Assume-Customer-Id' => 'GLOBEX01']), 403, 'ASSUME_NOT_ALLOWED');
    }

    public function testAssumingAnUnknownAccountIs404(): void
    {
        $admin = $this->admin();

        $this->assertApiError($this->api('GET', '/api/v1/customer/usage', as: $admin, headers: ['X-Assume-Customer-Id' => 'NOPE0000']), 404, 'ASSUMED_CUSTOMER_NOT_FOUND');
    }

    public function testWritesWhileAssumingAreLogged(): void
    {
        $admin = $this->admin();
        $this->account('GLOBEX01', plan: 'starter');

        $this->api('PATCH', '/api/v1/customer/onboarding', ['completed' => true], as: $admin, headers: ['X-Assume-Customer-Id' => 'GLOBEX01']);

        $logged = $this->em()->getConnection()->fetchAssociative('SELECT admin_email, customer_id, method FROM impersonation_log');
        self::assertSame(['admin_email' => $admin, 'customer_id' => 'GLOBEX01', 'method' => 'PATCH'], $logged, 'D18: actions taken while impersonating are logged');
    }

    public function testHealth(): void
    {
        self::assertSame(['database' => 'ok'], $this->data($this->api('GET', '/api/v1/health')));
    }

    public function testAnUnknownApiRouteIsAJson404(): void
    {
        $this->assertApiError($this->api('GET', '/api/v1/nothing-here'), 404, 'NOT_FOUND');
    }
}
