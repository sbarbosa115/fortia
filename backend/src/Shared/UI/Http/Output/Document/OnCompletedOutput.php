<?php

namespace App\Shared\UI\Http\Output\Document;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * What happens when a questionnaire is completed (PRD §6.5), discriminated by type. In a respondent's session it is
 * reduced to {type} so the scoring configuration is never exposed (§8.4).
 */
final class OnCompletedOutput
{
    /**
     * @param list<array<string, mixed>>|null $products
     * @param list<TierOutput>|null           $tiers
     * @param list<TierTextOutput>|null       $recommendations
     * @param list<TierTextOutput>|null       $action_plan
     */
    public function __construct(
        #[OA\Property(enum: ['default', 'quiz_funnel', 'diagnostic', 'process_mapping'])]
        public readonly string $type,
        public readonly ?string $message = null,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(type: 'object', additionalProperties: true))]
        public readonly ?array $products = null,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(ref: new Model(type: TierOutput::class)))]
        public readonly ?array $tiers = null,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(ref: new Model(type: TierTextOutput::class)))]
        public readonly ?array $recommendations = null,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(ref: new Model(type: TierTextOutput::class)))]
        public readonly ?array $action_plan = null,
    ) {
    }

    /** @param array<string, mixed> $o */
    public static function fromArray(array $o): self
    {
        return new self(
            (string) ($o['type'] ?? 'default'),
            isset($o['message']) ? (string) $o['message'] : null,
            \is_array($o['products'] ?? null) ? array_values($o['products']) : null,
            \is_array($o['tiers'] ?? null) ? TierOutput::list($o['tiers']) : null,
            \is_array($o['recommendations'] ?? null) ? TierTextOutput::list($o['recommendations']) : null,
            \is_array($o['action_plan'] ?? null) ? TierTextOutput::list($o['action_plan']) : null,
        );
    }

    /**
     * Only the type: what a respondent's session carries.
     *
     * @param array<string, mixed>|null $o
     */
    public static function typeOnly(?array $o): ?self
    {
        return null === $o ? null : new self((string) ($o['type'] ?? 'default'));
    }
}
