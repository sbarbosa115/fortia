<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Llm;

use App\Shared\Application\Llm\LanguageModel;
use App\Shared\Infrastructure\Llm\Fake\FakeLanguageModel;

/** LLM_PROVIDER picks the adapter: "anthropic" (the Claude API) or "fake" (offline, deterministic). */
final class LanguageModelFactory
{
    public function __construct(
        private readonly AnthropicLanguageModel $anthropic,
        private readonly FakeLanguageModel $fake,
    ) {
    }

    public function create(string $provider): LanguageModel
    {
        return 'anthropic' === $provider ? $this->anthropic : $this->fake;
    }
}
