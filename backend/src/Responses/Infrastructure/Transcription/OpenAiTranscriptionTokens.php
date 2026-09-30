<?php

namespace App\Responses\Infrastructure\Transcription;

use App\Responses\Application\Port\TranscriptionToken;
use App\Responses\Application\Port\TranscriptionTokens;
use App\Shared\Domain\Error\UpstreamFailed;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * An ephemeral client secret of the OpenAI Realtime API for a transcription session (PCM16 at 24 kHz, PRD §13.4),
 * valid about a minute. The API key never leaves the server.
 */
final class OpenAiTranscriptionTokens implements TranscriptionTokens
{
    public const PROVIDER = 'openai';
    private const URL = 'https://api.openai.com/v1/realtime/client_secrets';
    private const TTL = 60;

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire(env: 'OPENAI_API_KEY')]
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-transcribe',
    ) {
    }

    public function issue(): TranscriptionToken
    {
        try {
            $response = $this->http->request('POST', self::URL, [
                'auth_bearer' => $this->apiKey,
                'timeout' => 10,
                'json' => [
                    'expires_after' => ['anchor' => 'created_at', 'seconds' => self::TTL],
                    'session' => [
                        'type' => 'transcription',
                        'audio' => ['input' => [
                            'format' => ['type' => 'audio/pcm', 'rate' => 24000],
                            'transcription' => ['model' => $this->model],
                        ]],
                    ],
                ],
            ]);
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new UpstreamFailed('INTERNAL_ERROR', 'The transcription service is unavailable.', [], $e);
        }
        $token = $data['value'] ?? ($data['client_secret']['value'] ?? null);
        if (!\is_string($token) || '' === $token) {
            throw new UpstreamFailed('INTERNAL_ERROR', 'The transcription service did not issue a token.');
        }

        return new TranscriptionToken($token, self::PROVIDER, self::TTL);
    }
}
