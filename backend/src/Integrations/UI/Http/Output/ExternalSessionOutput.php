<?php

namespace App\Integrations\UI\Http\Output;

use OpenApi\Attributes as OA;

/** A session's answers as webhooks send them (PRD §7.14 value format): {id, answers: [{title, value, min?, max?}]}. */
final class ExternalSessionOutput
{
    /** @param list<array<string, mixed>> $answers */
    public function __construct(
        public readonly string $id,
        #[OA\Property(type: 'array', items: new OA\Items(
            required: ['title', 'value'],
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'value', description: 'A string, a number or a list, by control type (PRD §7.14)'),
                new OA\Property(property: 'min', type: 'number'),
                new OA\Property(property: 'max', type: 'number'),
            ],
            type: 'object',
        ))]
        public readonly array $answers,
    ) {
    }
}
