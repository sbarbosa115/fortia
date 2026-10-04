<?php

namespace App\Responses\Infrastructure\Transcription;

use App\Responses\Application\Port\TranscriptionTokens;
use App\Shared\Application\Llm\OpenAiKeys;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** TRANSCRIPTION_PROVIDER picks the adapter: "openai" (the account's key or OPENAI_API_KEY) or "browser" (default). */
final class TranscriptionTokensFactory
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly OpenAiKeys $keys,
    ) {
    }

    public function create(string $provider): TranscriptionTokens
    {
        if (OpenAiTranscriptionTokens::PROVIDER === $provider) {
            return new OpenAiTranscriptionTokens($this->http, $this->keys, new BrowserTranscriptionTokens());
        }

        return new BrowserTranscriptionTokens();
    }
}
