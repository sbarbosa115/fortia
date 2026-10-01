<?php

namespace App\Chat\Application\Tool;

use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Ids;

/**
 * A tool's input as the model sent it, read field by field with the shape checks the HTTP inputs make (a refusal is
 * 400 VALIDATION_ERROR "field: message", which goes back to the model so it can fix the call).
 */
final class ToolInput
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    public function has(string $field): bool
    {
        return \array_key_exists($field, $this->values);
    }

    public function string(string $field, int $max = 200): string
    {
        $value = $this->optionalString($field, $max);
        if (null === $value) {
            throw self::invalid($field, 'This value is required.');
        }

        return $value;
    }

    public function optionalString(string $field, int $max = 200): ?string
    {
        $value = $this->values[$field] ?? null;
        if (null === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw self::invalid($field, 'This value should be a string.');
        }
        $value = trim($value);
        if (mb_strlen($value) > $max) {
            throw self::invalid($field, "This value is too long (at most $max characters).");
        }

        return '' === $value ? null : $value;
    }

    public function uuid(string $field): string
    {
        $value = $this->string($field, 36);
        if (!Ids::isUuid4(strtolower($value))) {
            throw self::invalid($field, 'This value should be a valid id.');
        }

        return strtolower($value);
    }

    public function optionalUuid(string $field): ?string
    {
        return null === ($this->values[$field] ?? null) ? null : $this->uuid($field);
    }

    public function bool(string $field): bool
    {
        $value = $this->values[$field] ?? null;
        if (!\is_bool($value)) {
            throw self::invalid($field, 'This value should be true or false.');
        }

        return $value;
    }

    public function int(string $field, int $default, int $min, int $max): int
    {
        $value = $this->values[$field] ?? null;
        if (null === $value) {
            return $default;
        }
        if (!\is_int($value) || $value < $min || $value > $max) {
            throw self::invalid($field, "This value should be a whole number from $min to $max.");
        }

        return $value;
    }

    /** @param list<string> $choices */
    public function choice(string $field, array $choices, ?string $default = null): string
    {
        $value = $this->values[$field] ?? $default;
        if (!\is_string($value) || !\in_array($value, $choices, true)) {
            throw self::invalid($field, 'The value must be one of: '.implode(', ', $choices).'.');
        }

        return $value;
    }

    /** The page size of a list shown in the chat: 5 or 10 rows (PRD §7.19). */
    public function pageSize(): int
    {
        $value = $this->values['page_size'] ?? 5;

        return 10 === $value ? 10 : 5;
    }

    public function offset(): int
    {
        return $this->int('offset', 0, 0, 100_000);
    }

    /** @return array<string, mixed> */
    public function object(string $field): array
    {
        $value = $this->values[$field] ?? null;
        if (!\is_array($value)) {
            throw self::invalid($field, 'This value should be an object.');
        }

        return $value;
    }

    /**
     * Only the fields named in $allowed, as sent (null included): the partial-update convention of the HTTP inputs.
     *
     * @param list<string> $allowed
     *
     * @return array<string, mixed>
     */
    public function only(array $allowed): array
    {
        return array_intersect_key($this->values, array_flip($allowed));
    }

    public static function invalid(string $field, string $message): Rejected
    {
        return new Rejected('VALIDATION_ERROR', "$field: $message");
    }
}
