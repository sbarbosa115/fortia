<?php

namespace App\Shared\UI\Http\Output\Document;

use OpenApi\Attributes as OA;

/**
 * A node of a flow (PRD §6.6): {state_id (15 chars), type, parameters, outputs, next?}. A "questionnaire" state has
 * parameters.questionnaire_id; a "prompt" state the storage key of its prompt text.
 */
final class FlowStateOutput
{
    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $outputs
     */
    public function __construct(
        public readonly string $state_id,
        #[OA\Property(enum: ['questionnaire', 'regular', 'quiz_funnel', 'diagnostic', 'prompt', 'result'])]
        public readonly string $type,
        #[OA\Property(type: 'object', additionalProperties: true)]
        public readonly array|\stdClass $parameters,
        #[OA\Property(type: 'object', additionalProperties: true)]
        public readonly array|\stdClass $outputs,
        public readonly ?string $next,
    ) {
    }

    /** @param array<string, mixed> $s */
    public static function fromArray(array $s): self
    {
        $parameters = \is_array($s['parameters'] ?? null) && [] !== $s['parameters'] ? $s['parameters'] : new \stdClass();
        $outputs = \is_array($s['outputs'] ?? null) && [] !== $s['outputs'] ? $s['outputs'] : new \stdClass();

        return new self((string) ($s['state_id'] ?? ''), (string) ($s['type'] ?? ''), $parameters, $outputs, isset($s['next']) ? (string) $s['next'] : null);
    }
}
