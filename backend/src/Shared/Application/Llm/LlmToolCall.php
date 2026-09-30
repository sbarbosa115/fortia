<?php

declare(strict_types=1);

namespace App\Shared\Application\Llm;

final class LlmToolCall
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $input,
    ) {
    }
}
