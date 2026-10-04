<?php

namespace App\Shared\Infrastructure\Llm;

use App\Shared\Application\Llm\LanguageModel;
use App\Shared\Infrastructure\Llm\Fake\FakeLanguageModel;

/** LLM_PROVIDER picks the adapter: "openai" (the Responses API) or "fake" (offline, deterministic). */
final class LanguageModelFactory
{
    public function __construct(
        private readonly OpenAiLanguageModel $openAi,
        private readonly FakeLanguageModel $fake,
    ) {
    }

    public function create(string $provider): LanguageModel
    {
        return 'openai' === $provider ? $this->openAi : $this->fake;
    }
}
