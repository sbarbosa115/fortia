<?php

namespace App\Shared\UI\Http\Output\Document;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * A scored result (PRD §6.10 DiagnosticResult): the total, the score per category, the visible tiers, and the
 * recommendations and action plan per tier.
 */
final class DiagnosticResultOutput
{
    /**
     * @param array{value: float, max: float}                                 $score
     * @param list<array{id: string, name: string, score: float, max: float}> $categories
     * @param list<TierOutput>                                                $tiers
     * @param list<TierTextOutput>                                            $recommendations
     * @param list<TierTextOutput>                                            $action_plan
     */
    public function __construct(
        #[OA\Property(enum: ['diagnostic'])]
        public readonly string $type,
        #[OA\Property(type: 'object', required: ['value', 'max'], properties: [new OA\Property(property: 'value', type: 'number'), new OA\Property(property: 'max', type: 'number')])]
        public readonly array $score,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object', required: ['id', 'name', 'score', 'max'], properties: [
            new OA\Property(property: 'id', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'score', type: 'number'),
            new OA\Property(property: 'max', type: 'number'),
        ]))]
        public readonly array $categories,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: TierOutput::class)))]
        public readonly array $tiers,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: TierTextOutput::class)))]
        public readonly array $recommendations,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: TierTextOutput::class)))]
        public readonly array $action_plan,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $score = \is_array($d['score'] ?? null) ? $d['score'] : [];
        $categories = [];
        foreach ((array) ($d['categories'] ?? []) as $c) {
            if (\is_array($c)) {
                $categories[] = ['id' => (string) ($c['id'] ?? ''), 'name' => (string) ($c['name'] ?? ''), 'score' => (float) ($c['score'] ?? 0), 'max' => (float) ($c['max'] ?? 0)];
            }
        }

        return new self(
            'diagnostic',
            ['value' => (float) ($score['value'] ?? 0), 'max' => (float) ($score['max'] ?? 0)],
            $categories,
            TierOutput::list((array) ($d['tiers'] ?? [])),
            TierTextOutput::list((array) ($d['recommendations'] ?? [])),
            TierTextOutput::list((array) ($d['action_plan'] ?? [])),
        );
    }
}
