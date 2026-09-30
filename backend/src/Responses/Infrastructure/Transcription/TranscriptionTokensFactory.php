<?php

namespace App\Responses\Infrastructure\Transcription;

use App\Responses\Application\Port\TranscriptionTokens;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** TRANSCRIPTION_PROVIDER picks the adapter: "openai" (needs OPENAI_API_KEY) or "browser" (the default). */
final class TranscriptionTokensFactory
{
    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    public function create(string $provider, string $openAiApiKey): TranscriptionTokens
    {
        if (OpenAiTranscriptionTokens::PROVIDER === $provider && '' !== trim($openAiApiKey)) {
            return new OpenAiTranscriptionTokens($this->http, $openAiApiKey);
        }

        return new BrowserTranscriptionTokens();
    }
}
