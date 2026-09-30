<?php

namespace App\Shared\Application\Llm;

final class LlmToolResult
{
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $content,
        public readonly bool $isError = false,
    ) {
    }
}
