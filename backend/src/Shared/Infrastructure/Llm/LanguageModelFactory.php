<?php

namespace App\Shared\Infrastructure\Llm;

use App\Shared\Application\Llm\LanguageModel;
use App\Shared\Infrastructure\Llm\Fake\FakeLanguageModel;

/**
 * LLM_PROVIDER picks the adapter: "openai" (the Responses API, always), "fake" (offline, deterministic, always) or
 * "auto" (OpenAI when the account or the platform has a key, the fake otherwise: AutoLanguageModel).
 */
final class LanguageModelFactory
{
    public function __construct(
        private readonly OpenAiLanguageModel $openAi,
        private readonly FakeLanguageModel $fake,
        private readonly AutoLanguageModel $auto,
    ) {
    }

    public function create(string $provider): LanguageModel
    {
        return match (strtolower(trim($provider))) {
            'openai' => $this->openAi,
            'auto' => $this->auto,
            default => $this->fake,
        };
    }
}
