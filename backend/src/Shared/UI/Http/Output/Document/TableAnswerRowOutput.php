<?php

namespace App\Shared\UI\Http\Output\Document;

use OpenApi\Attributes as OA;

/** A row of a table answer: the fixed row's label (null when the respondent added the rows) and its cells by column key. */
final class TableAnswerRowOutput
{
    /** @param array<string, string> $cells */
    public function __construct(
        public readonly ?string $label,
        #[OA\Property(type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'))]
        public readonly array $cells,
    ) {
    }
}
