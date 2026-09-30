<?php

namespace App\Shared\UI\Http\Output\Document;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** A flow (PRD §8.4 GET /flow/{identifier}). */
final class FlowOutput
{
    public const LAYOUT_BLOCKS = ['score', 'tier', 'categories', 'recommendations', 'action_plan', 'pdf', 'cta'];

    /**
     * @param list<FlowStateOutput>      $states
     * @param list<string>|null          $layout
     * @param array<string, string>|null $result_copy
     */
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $detail,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: FlowStateOutput::class)))]
        public readonly array $states,
        public readonly string $customer_id,
        public readonly string $questionnaire_id,
        public readonly ?string $source_url,
        public readonly ?CtaOutput $cta,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(type: 'string', enum: self::LAYOUT_BLOCKS))]
        public readonly ?array $layout,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties(type: 'string'))]
        public readonly ?array $result_copy,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {
    }

    /** @param array<string, mixed> $f the FlowView data */
    public static function fromArray(array $f): self
    {
        return new self(
            (string) $f['id'],
            (string) $f['slug'],
            (string) ($f['detail'] ?? ''),
            array_map(static fn (array $s): FlowStateOutput => FlowStateOutput::fromArray($s), array_values(array_filter((array) ($f['states'] ?? []), 'is_array'))),
            (string) $f['customer_id'],
            (string) $f['questionnaire_id'],
            isset($f['source_url']) ? (string) $f['source_url'] : null,
            CtaOutput::fromArray(\is_array($f['cta'] ?? null) ? $f['cta'] : null),
            \is_array($f['layout'] ?? null) ? array_values($f['layout']) : null,
            \is_array($f['result_copy'] ?? null) && [] !== $f['result_copy'] ? $f['result_copy'] : null,
            isset($f['created_at']) ? (string) $f['created_at'] : null,
            isset($f['updated_at']) ? (string) $f['updated_at'] : null,
        );
    }
}
