<?php

namespace App\Shared\Application\Llm;

final class LlmResponse
{
    /**
     * @param array<string, mixed>|null $json      the structured output, when the request had a JSON schema
     * @param list<LlmToolCall>         $toolCalls the tools the model asked to run (stop reason "tool_use")
     */
    public function __construct(
        public readonly string $text,
        public readonly ?array $json = null,
        public readonly array $toolCalls = [],
    ) {
    }

    /** @param array<string, mixed> $json */
    public static function json(array $json): self
    {
        return new self((string) json_encode($json), $json);
    }

    /** @param list<LlmToolCall> $calls */
    public static function toolCalls(array $calls, string $text = ''): self
    {
        return new self($text, null, $calls);
    }

    public function wantsTools(): bool
    {
        return [] !== $this->toolCalls;
    }
}
