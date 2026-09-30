<?php

namespace App\Questionnaires\UI\Http\Output;

use OpenApi\Attributes as OA;

/** A prompt of a chain (PRD §6.8) with its text loaded from object storage (§8.4 GET /questionnaire/{id}/prompts). */
final class PromptOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $questionnaire_id,
        public readonly string $customer_id,
        #[OA\Property(description: 'The key of the text in object storage: prompts/{customer_id}/{uuid}.txt')]
        public readonly string $s3_path,
        #[OA\Property(enum: ['diagnostic', 'quiz_funnel', 'result'], nullable: true)]
        public readonly ?string $outcome,
        public readonly int $order,
        public readonly string $text,
    ) {
    }

    /** @param array<string, mixed> $p */
    public static function fromArray(array $p): self
    {
        return new self((string) $p['id'], (string) $p['questionnaire_id'], (string) $p['customer_id'], (string) $p['s3_path'], isset($p['outcome']) ? (string) $p['outcome'] : null, (int) $p['order'], (string) $p['text']);
    }
}
