<?php

namespace App\Shared\UI\Http\Output\Document;

use OpenApi\Attributes as OA;

/** A validation of a control (PRD §6.5): min, max, required, or format ({type:'format', value:'letters,numbers'}). */
final class ValidationOutput
{
    public function __construct(
        #[OA\Property(enum: ['min', 'max', 'required', 'format'])]
        public readonly string $type,
        #[OA\Property(nullable: true, oneOf: [new OA\Schema(type: 'string'), new OA\Schema(type: 'number'), new OA\Schema(type: 'boolean')])]
        public readonly mixed $value = null,
        public readonly ?string $message = null,
        public readonly ?string $pattern = null,
    ) {
    }

    /** @param array<string, mixed> $v */
    public static function fromArray(array $v): self
    {
        return new self(
            (string) ($v['type'] ?? ''),
            $v['value'] ?? null,
            isset($v['message']) ? (string) $v['message'] : null,
            isset($v['pattern']) ? (string) $v['pattern'] : null,
        );
    }
}
