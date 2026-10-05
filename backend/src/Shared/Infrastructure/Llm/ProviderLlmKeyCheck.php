<?php

namespace App\Shared\Infrastructure\Llm;

use App\Shared\Application\Llm\LlmKeyCheck;
use App\Shared\Application\Llm\OpenAiKeys;

/**
 * "auto" and "openai" need a key (without one, auto falls to the fake and openai fails); "fake" is offline on purpose
 * (tests, demos) and never lacks one.
 */
final class ProviderLlmKeyCheck implements LlmKeyCheck
{
    public function __construct(
        private readonly OpenAiKeys $keys,
        private readonly string $provider,
    ) {
    }

    public function missingKey(?string $customerId): bool
    {
        return \in_array(strtolower(trim($this->provider)), ['auto', 'openai'], true) && '' === $this->keys->keyFor($customerId);
    }
}
