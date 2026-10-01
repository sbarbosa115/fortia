<?php

namespace App\Branding\UI\Http\Output;

use OpenApi\Attributes as OA;

/** PRD §8.5 GET /styles: the account's styles (camelCase, §6.18), or null when it has none. */
final class StylesOutput
{
    /** @param array<string, mixed>|null $styles */
    public function __construct(
        #[OA\Property(type: 'object', nullable: true, additionalProperties: true, description: 'logoUrl, font, body, h1–h3, p, label, a, button.primary/secondary, input (PRD §6.18)')]
        public readonly ?array $styles,
    ) {
    }
}
