<?php

namespace App\Shared\Infrastructure\Llm\Fake;

use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Answers the fake language model's requests for one purpose, deterministically, so dev runs offline and tests
 * know what comes back. A context that calls the LLM adds one responder per purpose it uses (in its own
 * Infrastructure/Llm folder).
 */
#[AutoconfigureTag('app.fake_llm_responder')]
interface FakeLlmResponder
{
    public function supports(LlmRequest $request): bool;

    public function respond(LlmRequest $request): LlmResponse;
}
