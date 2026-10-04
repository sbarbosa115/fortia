<?php

namespace App\Shared\Application\Llm;

/**
 * The OpenAI API key a call is billed to: the account's own key (/profile › System) when it saved one, the platform's
 * OPENAI_API_KEY otherwise, or "" when neither is set.
 */
interface OpenAiKeys
{
    public function keyFor(?string $customerId): string;
}
