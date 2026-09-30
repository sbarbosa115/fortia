<?php

namespace App\Shared\Application\Llm;

/**
 * One turn of a conversation. An assistant turn may carry tool calls; the next user turn carries their results.
 */
final class LlmMessage
{
    /**
     * @param 'user'|'assistant'  $role
     * @param list<LlmToolCall>   $toolCalls   (assistant) the tools it asked to run
     * @param list<LlmToolResult> $toolResults (user) the results of the previous turn's tool calls
     */
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly array $toolResults = [],
    ) {
    }

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    /** @param list<LlmToolResult> $results */
    public static function toolResults(array $results): self
    {
        return new self('user', '', [], $results);
    }
}
