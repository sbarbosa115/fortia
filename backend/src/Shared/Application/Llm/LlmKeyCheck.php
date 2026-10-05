<?php

namespace App\Shared\Application\Llm;

/**
 * Whether an account can reach the real language model: without a key (the account's own in /profile › System, else
 * the platform's), LLM_PROVIDER=auto sends its calls to the offline fake, which is no use to a person chatting.
 */
interface LlmKeyCheck
{
    /** True when the account has no key to reach the real model. */
    public function missingKey(?string $customerId): bool;
}
