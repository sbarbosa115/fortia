<?php

namespace App\Shared\Application\Llm;

/**
 * The language model (PRD §13.3). Must support structured output (a JSON schema) and tool use.
 *
 * Two adapters: the Claude API (LLM_PROVIDER=anthropic) and a deterministic fake for dev and tests
 * (LLM_PROVIDER=fake), which answers each request through the FakeLlmResponder registered for its purpose.
 */
interface LanguageModel
{
    /** @throws LlmUnavailable when the provider fails, times out or refuses */
    public function complete(LlmRequest $request): LlmResponse;
}
