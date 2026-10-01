<?php

namespace App\Integrations\Application\Port;

/**
 * Sends one webhook request to a customer's receiver (PRD §7.14: 3 s to connect, 5 s to read). Never throws: a
 * network failure is a response without a status code.
 */
interface WebhookTransport
{
    /**
     * @param array<string, string> $headers
     */
    public function post(string $url, string $body, array $headers): WebhookResponse;
}
