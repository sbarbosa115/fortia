<?php

namespace App\Chat\UI\Http\Output;

use OpenApi\Attributes as OA;

/** A change the assistant proposed, waiting for the user's yes (PRD §7.19 write queue, at most 50). */
final class ChatPendingWriteOutput
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly string $id,
        public readonly string $tool,
        #[OA\Property(type: 'object', additionalProperties: true)]
        public readonly array $input,
        public readonly string $label,
    ) {
    }
}
