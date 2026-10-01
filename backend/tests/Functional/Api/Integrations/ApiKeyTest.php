<?php

namespace App\Tests\Functional\Api\Integrations;

use App\Billing\Application\Usage;
use App\Integrations\Domain\Model\ApiKey;
use App\Tests\Support\ApiTestCase;

/** API keys (PRD §6.20, §8.11 POST/GET/DELETE /api-keys, §10.17). */
final class ApiKeyTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clock()->set('2026-09-30T12:00:00Z');
    }

    public function testAnOwnerCreatesAKeyThatIsShownOnceAndStoredOnlyAsItsHash(): void
    {
        $owner = $this->account('ACME0001');

        $created = $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'CI pipeline', 'expiration_days' => 30], as: $owner), 201);

        self::assertMatchesRegularExpression('/^QAIRE-[0-9a-f]{64}$/', $created['api_key'], '§8.11: 201 {api_key: "QAIRE-" + 64 hex}');
        $this->em()->clear();
        $stored = $this->em()->find(ApiKey::class, hash('sha256', $created['api_key']));
        self::assertNotNull($stored, '§6.20: the id is the SHA-256 of the key');
        self::assertSame('2026-10-30T12:00:00Z', $stored->expiresAt()?->format('Y-m-d\TH:i:s\Z'), 'expiration_days counts from now');
        $list = $this->data($this->api('GET', '/api/v1/api-keys', as: $owner));
        self::assertCount(1, $list);
        self::assertSame(['id', 'name', 'created_at', 'expires_at', 'last_used_at'], array_keys($list[0]), '§8.11: the listing never carries the key itself');
        self::assertSame('CI pipeline', $list[0]['name']);
        self::assertNull($list[0]['last_used_at']);
        self::assertStringNotContainsString($created['api_key'], $this->api('GET', '/api/v1/api-keys', as: $owner)['body'], '§10.17: the plaintext is shown only once');
    }

    public function testAKeyWithoutExpirationNeverExpires(): void
    {
        $owner = $this->account('ACME0001');

        $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'Forever'], as: $owner), 201);

        self::assertNull($this->data($this->api('GET', '/api/v1/api-keys', as: $owner))[0]['expires_at']);
    }

    public function testTheListingShowsOnlyActiveKeysNewestFirstOfTheCallersAccount(): void
    {
        $owner = $this->account('ACME0001');
        $other = $this->account('GLOBEX01');
        $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'Old'], as: $owner), 201);
        $this->clock()->set('2026-09-30T13:00:00Z');
        $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'New'], as: $owner), 201);
        $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'Globex key'], as: $other), 201);
        $revoked = $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'Revoked'], as: $owner), 201);
        self::assertSame(204, $this->api('DELETE', '/api/v1/api-keys/'.hash('sha256', $revoked['api_key']), as: $owner)['status']);

        $names = array_column($this->data($this->api('GET', '/api/v1/api-keys', as: $owner)), 'name');

        self::assertSame(['New', 'Old'], $names, '§8.11: active ones only, newest first, never another account\'s');
    }

    public function testRevokingAnotherAccountsKeyIs404AndKeepsItActive(): void
    {
        $owner = $this->account('ACME0001');
        $other = $this->account('GLOBEX01');
        $key = $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'Globex key'], as: $other), 201)['api_key'];
        $id = hash('sha256', $key);

        $this->assertApiError($this->api('DELETE', '/api/v1/api-keys/'.$id, as: $owner), 404, 'API_KEY_NOT_FOUND', 'another tenant\'s id is 404, never 403');
        $this->assertApiError($this->api('DELETE', '/api/v1/api-keys/not-a-key', as: $owner), 404, 'API_KEY_NOT_FOUND');

        self::assertCount(1, $this->data($this->api('GET', '/api/v1/api-keys', as: $other)));
        self::assertSame(204, $this->api('DELETE', '/api/v1/api-keys/'.$id, as: $other)['status']);
        $this->assertApiError($this->api('DELETE', '/api/v1/api-keys/'.$id, as: $other), 404, 'API_KEY_NOT_FOUND', 'a revoked key is gone for its owner too');
    }

    public function testTheNameIsRequiredAndBoundedAndExpirationIsWithinTenYears(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('POST', '/api/v1/api-keys', [], as: $owner), 400, 'VALIDATION_ERROR', '§8.11: name 1–100');
        $this->assertApiError($this->api('POST', '/api/v1/api-keys', ['name' => str_repeat('a', 101)], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('POST', '/api/v1/api-keys', ['name' => 'x', 'expiration_days' => 0], as: $owner), 400, 'VALIDATION_ERROR', '§8.11: expiration_days 1–3650');
        $this->assertApiError($this->api('POST', '/api/v1/api-keys', ['name' => 'x', 'expiration_days' => 3651], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('POST', '/api/v1/api-keys', ['name' => 'x', 'scope' => 'all'], as: $owner), 400, 'VALIDATION_ERROR', 'no extra fields');
    }

    public function testAPlanWithoutTheApiFeatureCannotCreateKeysButAnExhaustedQuotaCan(): void
    {
        $starter = $this->account('STARTER1', plan: 'starter');
        $this->assertApiError($this->api('POST', '/api/v1/api-keys', ['name' => 'x'], as: $starter), 429, 'PLAN_LIMIT_REACHED', '§8.11: Feat(api)');

        $pro = $this->account('ACME0001');
        static::getContainer()->get(Usage::class)->set('ACME0001', ['api' => 5000]);
        $this->em()->flush();
        $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'x'], as: $pro), 201);
    }

    public function testAReadOnlyMemberCanListButNotCreateOrRevoke(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', groups: ['Customer-Read-Only']);
        $key = $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'CI'], as: $owner), 201)['api_key'];

        self::assertCount(1, $this->data($this->api('GET', '/api/v1/api-keys', as: 'reader@acme.test')));
        $this->assertApiError($this->api('POST', '/api/v1/api-keys', ['name' => 'x'], as: 'reader@acme.test'), 403, 'FORBIDDEN', '§10.17: key management needs write permission');
        $this->assertApiError($this->api('DELETE', '/api/v1/api-keys/'.hash('sha256', $key), as: 'reader@acme.test'), 403, 'FORBIDDEN');
    }

    public function testSigningInIsRequired(): void
    {
        self::assertSame(401, $this->api('GET', '/api/v1/api-keys')['status']);
        self::assertSame(401, $this->api('POST', '/api/v1/api-keys', ['name' => 'x'])['status']);
    }
}
