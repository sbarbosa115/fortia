<?php

namespace App\Shared\Infrastructure\Llm;

use App\Shared\Application\Llm\LanguageModel;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Application\Llm\OpenAiKeys;
use App\Shared\Infrastructure\Llm\Fake\FakeLanguageModel;

/**
 * LLM_PROVIDER=auto: each call goes to OpenAI when there is a key to bill it to (the account's own, saved in
 * /profile › System, else the platform's OPENAI_API_KEY), and to the offline fake when there is none. So saving a key
 * in the console is enough to talk to the real model, and a stack without keys still works.
 */
final class AutoLanguageModel implements LanguageModel
{
    public function __construct(
        private readonly OpenAiLanguageModel $openAi,
        private readonly FakeLanguageModel $fake,
        private readonly OpenAiKeys $keys,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        return '' === $this->keys->keyFor($request->customerId)
            ? $this->fake->complete($request)
            : $this->openAi->complete($request);
    }
}
