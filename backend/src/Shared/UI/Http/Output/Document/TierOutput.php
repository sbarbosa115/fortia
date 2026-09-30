<?php

namespace App\Shared\UI\Http\Output\Document;

/** A score band [min, max] with a name (PRD §6.5 OnCompleted.diagnostic). */
final class TierOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $min,
        public readonly float $max,
        public readonly bool $visible,
    ) {
    }

    /** @param array<string, mixed> $t */
    public static function fromArray(array $t): self
    {
        return new self((string) ($t['id'] ?? ''), (string) ($t['name'] ?? ''), isset($t['description']) ? (string) $t['description'] : null, (float) ($t['min'] ?? 0), (float) ($t['max'] ?? 0), (bool) ($t['visible'] ?? true));
    }

    /**
     * @param array<int|string, mixed> $tiers
     *
     * @return list<self>
     */
    public static function list(array $tiers): array
    {
        return array_map(static fn (array $t): self => self::fromArray($t), array_values(array_filter($tiers, 'is_array')));
    }
}
