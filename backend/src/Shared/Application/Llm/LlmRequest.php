<?php

namespace App\Shared\Application\Llm;

final class LlmRequest
{
    /** Quality-sensitive work: generating questionnaires, dashboards, styles, chat (more reasoning). */
    public const TIER_GENERATION = 'generation';
    /** Simple, cheap calls: recommending products, evaluating answers (less reasoning, same model). */
    public const TIER_FAST = 'fast';

    /**
     * @param string                    $purpose     what this call is for, e.g. the system prompt key
     *                                               ("followups--rules-to-evaluate-answers") or "chat"; the fake
     *                                               answers by it
     * @param list<LlmMessage>          $messages
     * @param array<string, mixed>|null $jsonSchema  structured output: the answer is JSON valid against it
     * @param list<LlmTool>             $tools
     * @param list<LlmAttachment>       $attachments files added to the first user message
     * @param string|null               $model       overrides LLM_MODEL for this call
     * @param array<string, mixed>      $context     free data for the fake responder (never sent to a provider)
     * @param string|null               $customerId  the account the call is for: billed to its own OpenAI key when
     *                                               it saved one, else to the platform's
     */
    public function __construct(
        public readonly string $purpose,
        public readonly string $system,
        public readonly array $messages,
        public readonly ?array $jsonSchema = null,
        public readonly array $tools = [],
        public readonly string $tier = self::TIER_GENERATION,
        public readonly array $attachments = [],
        public readonly ?string $model = null,
        public readonly int $maxTokens = 16000,
        public readonly array $context = [],
        public readonly ?string $customerId = null,
    ) {
    }

    /**
     * A one-shot request: a system prompt and one user message.
     *
     * @param array<string, mixed>|null $jsonSchema
     * @param array<string, mixed>      $context
     */
    public static function single(string $purpose, string $system, string $user, ?array $jsonSchema = null, string $tier = self::TIER_GENERATION, array $context = [], ?string $customerId = null): self
    {
        return new self($purpose, $system, [LlmMessage::user($user)], $jsonSchema, [], $tier, [], null, 16000, $context, $customerId);
    }
}
