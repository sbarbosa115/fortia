<?php

namespace App\Shared\UI\Http\Output\Document;

/**
 * A recommendation or an action of a tier (PRD §6.5): {tier_id, recommendation | action, visible}. In a result only
 * the text of the reached tier is sent (visible is then absent).
 */
final class TierTextOutput
{
    public function __construct(
        public readonly string $tier_id,
        public readonly ?string $recommendation = null,
        public readonly ?string $action = null,
        public readonly ?bool $visible = null,
    ) {
    }

    /** @param array<string, mixed> $t */
    public static function fromArray(array $t): self
    {
        return new self(
            (string) ($t['tier_id'] ?? ''),
            isset($t['recommendation']) ? (string) $t['recommendation'] : null,
            isset($t['action']) ? (string) $t['action'] : null,
            isset($t['visible']) ? (bool) $t['visible'] : null,
        );
    }

    /**
     * @param array<int|string, mixed> $rows
     *
     * @return list<self>
     */
    public static function list(array $rows): array
    {
        return array_map(static fn (array $t): self => self::fromArray($t), array_values(array_filter($rows, 'is_array')));
    }
}
