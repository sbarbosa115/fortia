<?php

namespace App\Integrations\Infrastructure\Http;

use App\Integrations\Application\Port\WebhookResponse;
use App\Integrations\Application\Port\WebhookTransport;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * POSTs to the customer's receiver with the PRD §7.14 limits: 3 s to connect, 5 s to read. The client refuses
 * private and loopback addresses (a webhook URL is the customer's input: no requests into our own network), and does
 * not follow redirects.
 */
final class HttpWebhookTransport implements WebhookTransport
{
    private const CONNECT_TIMEOUT = 3;
    private const READ_TIMEOUT = 5;

    private readonly HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = new NoPrivateNetworkHttpClient($client);
    }

    public function post(string $url, string $body, array $headers): WebhookResponse
    {
        try {
            $response = $this->client->request('POST', $url, [
                'headers' => $headers + ['User-Agent' => 'Mappi-Webhooks/1.0'],
                'body' => $body,
                'timeout' => self::READ_TIMEOUT,
                'max_duration' => self::CONNECT_TIMEOUT + self::READ_TIMEOUT,
                'max_redirects' => 0,
                'extra' => ['curl' => [\CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT]],
            ]);

            return WebhookResponse::status($response->getStatusCode());
        } catch (TimeoutExceptionInterface) {
            return WebhookResponse::failed('Timeout');
        } catch (ExceptionInterface $e) {
            return WebhookResponse::failed(mb_substr($e->getMessage(), 0, 200));
        }
    }
}
