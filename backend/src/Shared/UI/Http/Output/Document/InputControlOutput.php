<?php

namespace App\Shared\UI\Http\Output\Document;

use App\Shared\Domain\Document\ControlType;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * The answer field of a question (PRD §6.5 InputControl). value, timestamp, skipped and locked exist only in a
 * session: value is the selected value(s), the transcriptions of an audio answer, or the storage keys of files.
 */
final class InputControlOutput
{
    /**
     * @param list<OptionOutput>     $options
     * @param list<ValidationOutput> $validations
     */
    public function __construct(
        public readonly string $name,
        #[OA\Property(enum: ControlType::VALUES)]
        public readonly string $type,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: OptionOutput::class)))]
        public readonly array $options,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ValidationOutput::class)))]
        public readonly array $validations,
        #[OA\Property(nullable: true, oneOf: [new OA\Schema(type: 'string'), new OA\Schema(type: 'number'), new OA\Schema(type: 'boolean')])]
        public readonly mixed $default_value = null,
        #[OA\Property(nullable: true, oneOf: [new OA\Schema(type: 'string'), new OA\Schema(type: 'array', items: new OA\Items(type: 'string'))])]
        public readonly mixed $value = null,
        public readonly ?string $timestamp = null,
        public readonly ?bool $skipped = null,
        public readonly ?bool $locked = null,
    ) {
    }

    /** @param array<string, mixed> $c */
    public static function fromArray(array $c): self
    {
        return new self(
            (string) ($c['name'] ?? ''),
            (string) ($c['type'] ?? 'message'),
            array_map(static fn (array $o): OptionOutput => OptionOutput::fromArray($o), array_values(array_filter((array) ($c['options'] ?? []), 'is_array'))),
            array_map(static fn (array $v): ValidationOutput => ValidationOutput::fromArray($v), array_values(array_filter((array) ($c['validations'] ?? []), 'is_array'))),
            $c['default_value'] ?? null,
            $c['value'] ?? null,
            isset($c['timestamp']) ? (string) $c['timestamp'] : null,
            \array_key_exists('skipped', $c) ? (bool) $c['skipped'] : null,
            isset($c['locked']) ? (bool) $c['locked'] : null,
        );
    }
}
