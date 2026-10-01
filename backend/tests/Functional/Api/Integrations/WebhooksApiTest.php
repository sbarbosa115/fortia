<?php

namespace App\Tests\Functional\Api\Integrations;

use App\Tests\Support\ApiTestCase;

/** Webhook CRUD (PRD §6.21, §8.11 POST/GET/PUT/DELETE /webhooks, §10.17). */
final class WebhooksApiTest extends ApiTestCase
{
    public function testAnOwnerCreatesListsEditsAndDeletesAWebhook(): void
    {
        $owner = $this->account('ACME0001');

        $created = $this->data($this->api('POST', '/api/v1/webhooks', ['url' => 'https://hooks.acme.test/in'], as: $owner), 201);

        self::assertSame('https://hooks.acme.test/in', $created['url']);
        self::assertSame('questionnaire.completed', $created['event_type'], 'the only event type, by default');
        self::assertSame('POST', $created['method']);
        self::assertSame('ACME0001', $created['customer_id']);
        self::assertSame([$created['id']], array_column($this->data($this->api('GET', '/api/v1/webhooks', as: $owner)), 'id'));

        $updated = $this->data($this->api('PUT', '/api/v1/webhooks/'.$created['id'], ['url' => 'https://hooks.acme.test/v2'], as: $owner));
        self::assertSame('https://hooks.acme.test/v2', $updated['url'], '§8.11: PUT is partial');
        self::assertSame('questionnaire.completed', $updated['event_type']);

        self::assertSame(204, $this->api('DELETE', '/api/v1/webhooks/'.$created['id'], as: $owner)['status']);
        self::assertSame([], $this->data($this->api('GET', '/api/v1/webhooks', as: $owner)));
    }

    public function testOnlyHttpsUrlsAndTheKnownEventAndMethodAreAccepted(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('POST', '/api/v1/webhooks', ['url' => 'http://hooks.acme.test/in'], as: $owner), 400, 'VALIDATION_ERROR', '§6.21: https only');
        $this->assertApiError($this->api('POST', '/api/v1/webhooks', ['url' => 'https://'], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('POST', '/api/v1/webhooks', [], as: $owner), 400, 'VALIDATION_ERROR', 'url is required');
        $this->assertApiError($this->api('POST', '/api/v1/webhooks', ['url' => 'https://hooks.acme.test', 'event_type' => 'session.started'], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('POST', '/api/v1/webhooks', ['url' => 'https://hooks.acme.test', 'method' => 'GET'], as: $owner), 400, 'VALIDATION_ERROR');
        $id = $this->data($this->api('POST', '/api/v1/webhooks', ['url' => 'https://hooks.acme.test'], as: $owner), 201)['id'];
        $this->assertApiError($this->api('PUT', '/api/v1/webhooks/'.$id, [], as: $owner), 400, 'VALIDATION_ERROR', 'PUT needs at least one field');
        $this->assertApiError($this->api('PUT', '/api/v1/webhooks/'.$id, ['url' => null], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('PUT', '/api/v1/webhooks/'.$id, ['url' => 'http://x.test'], as: $owner), 400, 'VALIDATION_ERROR');
    }

    public function testAnotherAccountsWebhookIs404(): void
    {
        $owner = $this->account('ACME0001');
        $other = $this->account('GLOBEX01');
        $id = $this->data($this->api('POST', '/api/v1/webhooks', ['url' => 'https://hooks.globex.test'], as: $other), 201)['id'];

        self::assertSame([], $this->data($this->api('GET', '/api/v1/webhooks', as: $owner)), 'another account\'s webhooks are never listed');
        $this->assertApiError($this->api('PUT', '/api/v1/webhooks/'.$id, ['url' => 'https://evil.test'], as: $owner), 404, 'WEBHOOK_NOT_FOUND', '§8.11: 404 if it belongs to another account');
        $this->assertApiError($this->api('DELETE', '/api/v1/webhooks/'.$id, as: $owner), 404, 'WEBHOOK_NOT_FOUND');
        $this->assertApiError($this->api('GET', '/api/v1/webhooks/'.$id.'/deliveries', as: $owner), 404, 'WEBHOOK_NOT_FOUND');
        $this->assertApiError($this->api('DELETE', '/api/v1/webhooks/not-a-uuid', as: $owner), 400, 'INVALID_UUID');
        self::assertSame('https://hooks.globex.test', $this->data($this->api('GET', '/api/v1/webhooks', as: $other))[0]['url']);
    }

    public function testAPlanWithoutWebhooksCannotCreateOne(): void
    {
        $starter = $this->account('STARTER1', plan: 'starter');

        $this->assertApiError($this->api('POST', '/api/v1/webhooks', ['url' => 'https://hooks.acme.test'], as: $starter), 429, 'PLAN_LIMIT_REACHED', '§8.11: Feat(webhook)');
    }

    public function testAReadOnlyMemberCanListButNotChange(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', groups: ['Customer-Read-Only']);
        $id = $this->data($this->api('POST', '/api/v1/webhooks', ['url' => 'https://hooks.acme.test'], as: $owner), 201)['id'];

        self::assertCount(1, $this->data($this->api('GET', '/api/v1/webhooks', as: 'reader@acme.test')));
        $this->assertApiError($this->api('POST', '/api/v1/webhooks', ['url' => 'https://hooks.acme.test/2'], as: 'reader@acme.test'), 403, 'FORBIDDEN');
        $this->assertApiError($this->api('PUT', '/api/v1/webhooks/'.$id, ['url' => 'https://hooks.acme.test/2'], as: 'reader@acme.test'), 403, 'FORBIDDEN');
        $this->assertApiError($this->api('DELETE', '/api/v1/webhooks/'.$id, as: 'reader@acme.test'), 403, 'FORBIDDEN');
    }
}
