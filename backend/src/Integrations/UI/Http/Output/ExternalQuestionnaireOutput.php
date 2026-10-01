<?php

namespace App\Integrations\UI\Http\Output;

/** A questionnaire as the external API lists it (PRD §8.11). */
final class ExternalQuestionnaireOutput
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $flow_id,
        public readonly ?string $slug,
        public readonly string $title,
        public readonly ?string $description,
        public readonly bool $is_active,
        public readonly string $type,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function of(array $row): self
    {
        return new self(
            (string) $row['id'],
            null === $row['flow_id'] ? null : (string) $row['flow_id'],
            null === $row['slug'] ? null : (string) $row['slug'],
            (string) $row['title'],
            null === $row['description'] ? null : (string) $row['description'],
            (bool) $row['is_active'],
            (string) $row['type'],
            null === $row['created_at'] ? null : (string) $row['created_at'],
            null === $row['updated_at'] ? null : (string) $row['updated_at'],
        );
    }
}
