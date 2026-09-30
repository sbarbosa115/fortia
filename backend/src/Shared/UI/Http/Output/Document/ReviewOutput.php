<?php

namespace App\Shared\UI\Http\Output\Document;

use OpenApi\Attributes as OA;

/** A reviewer's decision on a follow-up answer (PRD §6.9). It only counts for the attempt it was made in. */
final class ReviewOutput
{
    public function __construct(
        #[OA\Property(enum: ['approved', 'rejected'])]
        public readonly string $status,
        public readonly ?string $comment,
        public readonly ?string $reviewed_at,
        public readonly int $attempt,
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromArray(array $r): self
    {
        return new self((string) ($r['status'] ?? ''), isset($r['comment']) ? (string) $r['comment'] : null, isset($r['reviewed_at']) ? (string) $r['reviewed_at'] : null, (int) ($r['attempt'] ?? 1));
    }
}
