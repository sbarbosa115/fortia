<?php

namespace App\Shared\UI\Http\Output\Document;

use OpenApi\Attributes as OA;

/** An option of a control (PRD §6.5): if value is null, the label is the value. */
final class OptionOutput
{
    /** @param list<string> $visibility */
    public function __construct(
        public readonly string $label,
        #[OA\Property(nullable: true, oneOf: [new OA\Schema(type: 'string'), new OA\Schema(type: 'number')])]
        public readonly mixed $value,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string', enum: ['male', 'female']))]
        public readonly array $visibility,
    ) {
    }

    /** @param array<string, mixed> $o */
    public static function fromArray(array $o): self
    {
        return new self((string) ($o['label'] ?? ''), $o['value'] ?? null, array_values((array) ($o['visibility'] ?? [])));
    }
}
