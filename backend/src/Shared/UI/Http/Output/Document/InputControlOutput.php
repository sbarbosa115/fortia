<?php

namespace App\Shared\UI\Http\Output\Document;

use App\Shared\Domain\Document\ControlType;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * The answer field of a question (PRD §6.5 InputControl). value, timestamp, skipped and locked exist only in a
 * session: value is the selected value(s), the transcriptions of an audio answer, the storage keys of files, or a
 * table's rows ({column value: text}). A table's options are its columns and `rows` its fixed rows; a file question
 * may have a template to download.
 */
final class InputControlOutput
{
    /**
     * @param list<OptionOutput>     $options
     * @param list<ValidationOutput> $validations
     * @param list<string>|null      $rows
     */
    public function __construct(
        public readonly string $name,
        #[OA\Property(enum: ControlType::VALUES)]
        public readonly string $type,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: OptionOutput::class)))]
        public readonly array $options,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ValidationOutput::class)))]
        public readonly array $validations,
        #[OA\Property(description: 'A table\'s fixed rows (none: the respondent adds rows)', type: 'array', items: new OA\Items(type: 'string'), nullable: true)]
        public readonly ?array $rows,
        #[OA\Property(ref: new Model(type: FileTemplateOutput::class), nullable: true)]
        public readonly ?FileTemplateOutput $template,
        #[OA\Property(nullable: true, oneOf: [new OA\Schema(type: 'string'), new OA\Schema(type: 'number'), new OA\Schema(type: 'boolean')])]
        public readonly mixed $default_value = null,
        #[OA\Property(nullable: true, oneOf: [
            new OA\Schema(type: 'string'),
            new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
            new OA\Schema(type: 'array', items: new OA\Items(type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'))),
        ])]
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
            isset($c['rows']) && \is_array($c['rows']) ? array_values(array_map('strval', array_filter($c['rows'], 'is_scalar'))) : null,
            FileTemplateOutput::fromArray($c['template'] ?? null),
            $c['default_value'] ?? null,
            $c['value'] ?? null,
            isset($c['timestamp']) ? (string) $c['timestamp'] : null,
            \array_key_exists('skipped', $c) ? (bool) $c['skipped'] : null,
            isset($c['locked']) ? (bool) $c['locked'] : null,
        );
    }
}
