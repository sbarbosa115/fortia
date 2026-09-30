<?php

namespace App\Shared\Application\Llm;

final class LlmTool
{
    /** @param array<string, mixed> $inputSchema JSON schema of the tool's input */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
    ) {
    }
}
