<?php

namespace App\Tests\Functional\Api\Integrations;

use App\Integrations\Application\Port\WebhookResponse;
use App\Integrations\Application\Port\WebhookTransport;

/**
 * The test double of the webhook transport (config/services/integrations.yaml, when@test): it records each request
 * and answers with the next queued response (204 when none is queued). Nothing leaves the test.
 */
final class RecordingWebhookTransport implements WebhookTransport
{
    /** @var list<array{url: string, body: string, headers: array<string, string>}> */
    private array $requests = [];

    /** @var list<WebhookResponse> */
    private array $queued = [];

    public function post(string $url, string $body, array $headers): WebhookResponse
    {
        $this->requests[] = ['url' => $url, 'body' => $body, 'headers' => $headers];

        return array_shift($this->queued) ?? WebhookResponse::status(204);
    }

    public function willAnswer(WebhookResponse ...$responses): void
    {
        $this->queued = array_values([...$this->queued, ...$responses]);
    }

    /** @return list<array{url: string, body: string, headers: array<string, string>}> */
    public function requests(): array
    {
        return $this->requests;
    }

    public function reset(): void
    {
        $this->requests = [];
        $this->queued = [];
    }
}
